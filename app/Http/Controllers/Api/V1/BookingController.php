<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentRequirementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\AnalyticsEvent;
use App\Models\ServiceRequest;
use App\Models\Service;
use App\Models\Address;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Models\ServiceCompletionDocument;
use App\Services\PricingService;
use App\Services\LedgerService;
use App\Services\PromoService;
use App\Services\PromoException;
use App\Services\MembershipService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    protected $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/pdf'
    ];

    protected $maxFileSize = 10485760; // 10MB

    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'items.service',
            'serviceRequests.address',
            'serviceRequests.partner.user',
            'user'
        ])->where('user_id', $request->user()->user_id);

        // Filter by multiple statuses if 'statuses' parameter is present
        if ($request->has('statuses')) {
            $statuses = explode(',', $request->query('statuses'));
            $statuses = array_map('trim', $statuses);

            Log::info('Bookings filter request', [
                'user_id' => $request->user()->user_id,
                'requested_statuses' => $statuses
            ]);

            // Statuses an order may be shown in (upcoming + past lists).
            // Pre-payment states (created / pending / pending_payment) are
            // deliberately excluded: an unpaid online booking is NOT a booked
            // appointment and must never surface, no matter what the client
            // requests (the FE used to ask for them explicitly).
            $orderStatuses = array_intersect($statuses, array_diff(
                \App\Helpers\StatusEnumHelper::getOrderStatusValues(),
                ['created', 'pending', 'pending_payment']
            ));

            Log::info('Filtered order statuses', [
                'order_statuses' => $orderStatuses
            ]);

            if (!empty($orderStatuses)) {
                $query->whereIn('status', $orderStatuses);
            }
        } else {
            // Default: exclude created, pending and pending_payment orders
            // (pre-payment / unpaid states) when no status filter is specified
            $query->whereNotIn('status', ['created', 'pending', 'pending_payment']);
        }

        $perPage = $request->integer('per_page', 100);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 100;
        }

        $orders = $query->latest()->paginate($perPage);

        Log::info('Bookings query result', [
            'total_found' => $orders->total(),
            'per_page' => $orders->perPage(),
            'current_page' => $orders->currentPage()
        ]);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders)
        ]);
    }

    public function show(Request $request, $orderId): JsonResponse
    {
        $order = Order::with([
            'items.service',
            'serviceRequests.address',
            'serviceRequests.partner.user',
            'user'
        ])->where('user_id', $request->user()->user_id)
          ->where('order_id', $orderId)
          ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order)
        ]);
    }

    /**
     * Unpaid online orders for the current customer (the "Awaiting Payment"
     * section). These are NOT booked appointments and stay out of the regular
     * index, but the customer must be able to reach them to pay.
     */
    public function unpaidBookings(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $orders = Order::with([
            'items.service',
            'serviceRequests.address',
            'serviceRequests.partner.user',
            'user'
        ])
        ->where('user_id', $userId)
        // The customer closed this failed attempt themselves.
        ->whereNull('payment_dismissed_at')
        ->where(function ($query) {
            // 1) Online orders still owed money (abandoned checkout or a
            //    gateway session that never landed).
            $query->where(function ($q) {
                $q->where('payment_method', 'online')
                    ->whereIn('status', ['created', 'pending', 'pending_payment'])
                    ->where('payment_status', '!=', 'paid');
            });

            // 1b) Online orders whose gateway attempt FAILED or expired within
            //     the last 24h. They are already cancelled by the payment
            //     failure/expiry path, but retry-payment revives them with a
            //     fresh session and its requests restored - so keeping them
            //     reachable for a day lets a customer finish a booking they
            //     abandoned instead of rebuilding it from scratch.
            //
            //     Deliberately only 'cancelled': CancellationService sets
            //     payment_status='failed' on every unpaid cancellation, so
            //     including 'cancelled_by_customer' would resurface a booking
            //     the customer just chose to delete - with no Dismiss action,
            //     for 24 hours.
            $query->orWhere(function ($q) {
                $q->where('payment_method', 'online')
                    ->where('payment_status', 'failed')
                    ->where('status', 'cancelled')
                    ->where('created_at', '>=', now()->subHours(24));
            });

            // 2) PAS awaiting ONLINE settlement: every service request is
            //    finished, no cash was declared, and no payment has landed.
            //    The customer pays from right here (Pay Now).
            $query->orWhere(function ($q) {
                $q->where('payment_method', 'pas')
                    ->where('payment_status', '!=', 'paid')
                    ->where(function ($qq) {
                        $qq->whereNull('payment_received')
                            ->orWhere('payment_received', '!=', 'cash');
                    })
                    ->whereHas('serviceRequests', function ($sr) {
                        $sr->where('status', 'completed');
                    })
                    ->whereDoesntHave('serviceRequests', function ($sr) {
                        $sr->whereIn('status', [
                            'pending',
                            'assigned',
                            'accepted',
                            'working',
                            'in_progress',
                        ]);
                    });
            });

            // 3) PAS cash already COLLECTED at the door: surfaces until the
            //    customer confirms (or disputes - disputed rows move to the
            //    details page / support).
            $query->orWhere(function ($q) {
                $q->where('payment_method', 'pas')
                    ->where('payment_received', 'cash')
                    ->where('cash_confirmed', false)
                    ->where('cash_disputed', false);
            });
        })
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders)
        ]);
    }

    public function store(BookingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Handle address - require existing address
            if (!$request->address_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a saved address or add a new address first.'
                ], 400);
            }

            // Use existing address
            $address = Address::where('user_id', $request->user()->user_id)
                ->where('address_id', $request->address_id)
                ->firstOrFail();

            // Idempotency guard: block rapid duplicate submissions (double-tap,
            // retries, stale bundles) that would create two orders for the same
            // user, services and schedule within a short window.
            $guardDate = Carbon::parse($request->scheduled_at);
            $guardServiceIds = collect($request->services)->pluck('service_id')->all();

            // Per-service pre-pay rule: if any requested service requires pre-payment
            // (requires_prepay = true), the whole order must be prepaid online.
            // The client can't opt into pay-after-service for such services.
            $requiresPrepay = \App\Models\Service::whereIn('service_id', $guardServiceIds)
                ->where('requires_prepay', true)
                ->exists();

            if ($requiresPrepay && $request->input('payment_method', 'online') !== 'online') {
                Log::info('Booking forced to online prepayment: service requires pre-payment', [
                    'user_id' => $request->user()->user_id,
                    'service_ids' => $guardServiceIds,
                ]);
            }

            $existingOrder = Order::where('user_id', $request->user()->user_id)
                ->whereIn('status', [
                    'created', 'pending_payment', 'confirmed', 'partner_assigned',
                    'assigned', 'accepted', 'enroute', 'in_progress', 'working',
                ])
                ->where('created_at', '>=', now()->subSeconds(60))
                ->whereHas('serviceRequests', function ($q) use ($guardDate) {
                    $q->where('service_date', $guardDate->format('Y-m-d'))
                        ->where('start_time', $guardDate->format('H:i:s'));
                })
                ->whereHas('items', function ($q) use ($guardServiceIds) {
                    $q->whereIn('service_id', $guardServiceIds);
                })
                ->orderByDesc('created_at')
                ->first();

            if ($existingOrder) {
                Log::warning('Duplicate booking request blocked', [
                    'user_id' => $request->user()->user_id,
                    'existing_order_id' => $existingOrder->order_id,
                ]);

                // A promo sent with the duplicate must not be swallowed: the
                // order that already exists decides the money, and if it was
                // created without the code the customer has to be told.
                $requestedPromo = strtoupper(trim((string) $request->input('promo_code')));

                // The duplicate response must mirror the EXISTING order's
                // payment method, never the incoming request's: a prepay
                // (online) order must keep being asked to pay, and only a
                // genuine PAS order may be confirmed without payment.
                if ($existingOrder->payment_method === 'pas') {
                    return response()->json([
                        'success' => true,
                        'duplicate' => true,
                        'order_id' => $existingOrder->order_id,
                        'payment_method' => 'pas',
                        'promo_code' => $existingOrder->promo_code,
                        'discount_amount' => (float) $existingOrder->discount_amount,
                        'total_amount' => (float) $existingOrder->total_amount,
                        'promo_note' => $requestedPromo !== '' && !$existingOrder->promo_code
                            ? 'Promo code not applied: a booking for this slot already exists without it.'
                            : null,
                        'message' => 'Booking created successfully. Payment will be collected after service completion.',
                        'status' => $existingOrder->fresh()->status,
                        'note' => 'For cash collection, commission is deducted from partner balance after customer confirmation.'
                    ], 201);
                }

                // Online payment: return the same payment session for the existing order
                $totalAmount = $existingOrder->total_amount;
                $paymentPayload = [
                    'amount' => (float) $existingOrder->subtotal - (float) $existingOrder->discount_amount,
                    'tax_amount' => $existingOrder->tax_amount,
                    'product_service_charge' => 0,
                    'product_delivery_charge' => 0,
                    'total_amount' => $totalAmount,
                    'transaction_uuid' => $existingOrder->transaction_uuid,
                    'product_code' => config('payment.gateways.esewa.product_code', 'EPAYTEST'),
                    'success_url' => url('/api/v1/webhooks/esewa/success'),
                    'failure_url' => url('/api/v1/webhooks/esewa/failure'),
                    'signed_field_names' => 'total_amount,transaction_uuid,product_code',
                ];
                $message = "total_amount={$paymentPayload['total_amount']},transaction_uuid={$paymentPayload['transaction_uuid']},product_code={$paymentPayload['product_code']}";
                $paymentPayload['signature'] = base64_encode(
                    hash_hmac('sha256', $message, config('payment.gateways.esewa.secret_key'), true)
                );

                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'order_id' => $existingOrder->order_id,
                    'payment_method' => 'online',
                    'promo_code' => $existingOrder->promo_code,
                    'discount_amount' => (float) $existingOrder->discount_amount,
                    'total_amount' => (float) $existingOrder->total_amount,
                    'promo_note' => $requestedPromo !== '' && !$existingOrder->promo_code
                        ? 'Promo code not applied: a booking for this slot already exists without it.'
                        : null,
                    'payment_url' => rtrim(config('payment.gateways.esewa.form_url') ?? config('payment.gateways.esewa.base_url', 'https://uat.esewa.com.np'), '/') . '/api/epay/main/v2/form',
                    'payload' => $paymentPayload,
                    'message' => 'Booking created successfully. Proceed to payment.'
                ], 201);
            }

            // Calculate pricing for all services
            $totalSubtotal = 0;
            $serviceDetails = [];
            $orderItems = [];
            // Same lines in the shape the promo engine needs to test a
            // service-scoped code against the right part of the cart.
            $promoItems = [];

            foreach ($request->services as $serviceData) {
                // Get the service to calculate pricing
                $service = Service::with('pricing')->findOrFail($serviceData['service_id']);
                $pricing = $service->pricing->where('is_active', true)->first();
                $unitPrice = $pricing ? $pricing->base_price : 100; // Fallback price

                $quantity = $serviceData['quantity'];
                $serviceSubtotal = $unitPrice * $quantity;
                $totalSubtotal += $serviceSubtotal;

                // Store service details for narration
                $serviceDetails[] = [
                    'name' => $service->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $serviceSubtotal
                ];

                // Prepare order items for creation
                $orderItems[] = [
                    'service_id' => $serviceData['service_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $serviceSubtotal,
                ];

                $promoItems[] = [
                    'service_id' => (int) $serviceData['service_id'],
                    'subtotal' => $serviceSubtotal,
                ];
            }

            // Promo code: validate against the gross subtotal before tax.
            // An invalid code rejects the whole booking - the customer asked
            // for it to be applied, so silently ignoring it would surprise
            // them at payment time.
            $discountAmount = 0.0;
            $appliedPromotion = null;
            $promoCode = trim((string) $request->input('promo_code'));
            if ($promoCode !== '') {
                try {
                    $promoResult = PromoService::validate($request->user(), $promoCode, (float) $totalSubtotal, $promoItems);
                    $appliedPromotion = $promoResult['promotion'];
                    $discountAmount = $promoResult['discount'];
                } catch (PromoException $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'valid' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }
            }

            // Homify Plus membership: percent off the remaining base after
            // any promo, capped by the plan perk. Ships inside
            // discount_amount so tax, gateway payloads and settlement math
            // stay exactly as before; membership_discount records the share.
            $membershipDiscount = 0.0;
            $activeMembership = MembershipService::activeFor($request->user());
            if ($activeMembership) {
                $membershipDiscount = MembershipService::discountFor(
                    $activeMembership,
                    $totalSubtotal - $discountAmount
                );
                $discountAmount += $membershipDiscount;
            }

            // Calculate tax on the discounted base (subtotal - promo - membership)
            $taxableBase = $totalSubtotal - $discountAmount;
            $taxAmount = PricingService::calculateTax($taxableBase);

            // Calculate platform fee using PricingService for proper commission calculation
            // For multiple services, we'll use the primary service's category as fallback
            $primaryService = Service::with('category')->findOrFail($request->services[0]['service_id']);
            $serviceCategory = $primaryService->category->name ?? 'default';
            $city = $address->city ?? 'Kathmandu';

            $totalWithTax = $taxableBase + $taxAmount;
            $commissionBreakdown = PricingService::calculateEarningsBreakdown(
                $totalWithTax,
                $serviceCategory,
                $city
            );

            $platformFee = $commissionBreakdown['platform_fee'];
            $partnerShare = $commissionBreakdown['partner_earning'];

            $totalAmount = $totalWithTax;
            $finalPayable = $totalAmount;

            // Generate transaction UUID for eSewa
            $transactionUuid = (string) Str::uuid();

            // Create detailed narration for services
            $serviceNarration = implode(', ', array_map(function ($detail) {
                return "{$detail['name']} x{$detail['quantity']} (Rs.{$detail['total_price']})";
            }, $serviceDetails));

            // Get payment method from request (default to online)
            $paymentMethod = $request->input('payment_method', 'online');

            // Validate payment method; 'cod' is accepted as a legacy alias for 'pas'
            if (!in_array($paymentMethod, ['online', 'pas', 'cod'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment method. Must be "online" or "pas".'
                ], 400);
            }
            $paymentMethod = $paymentMethod === 'cod' ? 'pas' : $paymentMethod;

            // Enforce per-service pre-pay rule: services that require pre-payment
            // force the whole order to be prepaid online. The client cannot opt
            // into pay-after-service for such services.
            if ($requiresPrepay && $paymentMethod !== 'online') {
                DB::rollBack();
                Log::warning('Booking rejected: pay-after-service not allowed for prepay-required service', [
                    'user_id' => $request->user()->user_id,
                    'service_ids' => $guardServiceIds,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'This service requires payment in advance. Pay After Service is not available for this booking - please pay online.',
                    'payment_method' => 'online',
                ], 422);
            }

            // Create the order with payment_status based on payment method
            $orderData = [
                'user_id' => $request->user()->user_id,
                'status' => 'created',
                'payment_method' => $paymentMethod,
                'subtotal' => $totalSubtotal,
                'discount_amount' => $discountAmount,
                'membership_discount' => $membershipDiscount,
                'promo_code' => $appliedPromotion?->promo_code,
                'tax_amount' => $taxAmount,
                'platform_fee' => $platformFee,
                'total_amount' => $totalAmount,
                'final_payable' => $finalPayable,
                'transaction_uuid' => $transactionUuid,
            ];

            // Payment is collected either online (prepaid) or after service (PAS).
            // PAS orders are settled at completion, so they never block order creation.
            $orderData['payment_status'] = 'pending';

            // Online prepay orders carry a payment deadline so the expiry job
            // can cancel them (order + service requests) if the customer never
            // pays. PAS orders have no deadline - payment happens at completion.
            if ($paymentMethod === 'online') {
                $orderData['payment_expires_at'] = now()->addMinutes(15);
            }

            $order = Order::create($orderData);

            // Burn the promo usage inside the transaction: a rollback here
            // releases the slot, so usage_limit can never be overspent.
            if ($appliedPromotion) {
                PromoService::recordUsage($request->user(), $appliedPromotion);
            }

            // Create multiple order items and store references
            $createdOrderItems = [];
            foreach ($orderItems as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->order_id,
                    'service_id' => $itemData['service_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                ]);
                $createdOrderItems[] = $orderItem;
            }

            $serviceDate = Carbon::parse($request->scheduled_at);

            // Check if customer selected a preferred partner
            $preferredPartnerId = $request->input('preferred_partner_id');
            $partnerSelectionReason = $request->input('partner_selection_reason');

            // Validate preferred partner if provided
            if ($preferredPartnerId) {
                $preferredPartner = \App\Models\PartnerProfile::with('user')
                    ->where('partner_id', $preferredPartnerId)
                    ->where('status', 'active')
                    ->whereHas('user', function ($q) {
                        $q->where('is_active', true);
                    })
                    ->first();

                if (!$preferredPartner) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected partner is not available for booking'
                    ], 400);
                }

                // Check if partner provides all the requested services
                $requestedServiceIds = collect($request->services)->pluck('service_id')->toArray();
                $partnerServiceIds = $preferredPartner->services()->pluck('services.service_id')->toArray();
                $missingServices = array_diff($requestedServiceIds, $partnerServiceIds);

                if (!empty($missingServices)) {
                    $missingServiceNames = \App\Models\Service::whereIn('service_id', $missingServices)
                        ->pluck('name')->toArray();

                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected partner does not provide the following services: ' . implode(', ', $missingServiceNames)
                    ], 400);
                }

                // Check partner availability for the scheduled date/time,
                // honouring working windows and the full service duration so
                // two bookings can never overlap on the same partner.
                $availability = app(\App\Services\AvailabilityService::class);
                $duration = $availability->durationForServices($requestedServiceIds);
                $slot = $availability->check((int) $preferredPartnerId, $serviceDate, $duration);

                if (!$slot['available']) {
                    DB::rollBack();
                    $reason = match ($slot['reason']) {
                        'outside_hours' => 'Selected partner does not work at the requested time.',
                        'conflict' => 'Selected partner is already booked for that time slot.',
                        'past' => 'The requested time has already passed.',
                        default => 'Selected partner is not available at the requested time slot.',
                    };

                    return response()->json([
                        'success' => false,
                        'message' => $reason . ' Please choose a different time or partner.',
                        'reason' => $slot['reason'],
                    ], 400);
                }
            }

            // Create service requests for each order item (1 ServiceRequest per OrderItem).
            // An online-prepay order is NOT booked until the gateway settles:
            // the preferred partner is remembered on the request, but its
            // status stays 'pending' (and no acceptance deadline starts) until
            // PaymentService promotes it to 'assigned' on success. Creating it
            // as 'assigned' meant a failed/abandoned eSewa attempt still
            // booked the slot and put a job in the partner's queue.
            $isOnlinePrepay = $paymentMethod === 'online';

            foreach ($createdOrderItems as $orderItem) {
                $serviceRequestData = [
                    'order_id' => $order->order_id,
                    'order_item_id' => $orderItem->item_id, // Link to specific OrderItem
                    'user_address_id' => $address->address_id,
                    'status' => ($preferredPartnerId && !$isOnlinePrepay) ? 'assigned' : 'pending',
                    'service_date' => $serviceDate->format('Y-m-d'),
                    'start_time' => $serviceDate->format('H:i:s'),
                    'special_instructions' => $request->notes,
                ];

                // Remember the preferred partner. Only PAS starts the
                // acceptance clock here; online prepay starts it on payment.
                if ($preferredPartnerId) {
                    $serviceRequestData['partner_id'] = $preferredPartnerId;
                    $serviceRequestData['assignment_method'] = 'customer_selected';
                    $serviceRequestData['assignment_notes'] = "Customer selected partner. Reason: " . ($partnerSelectionReason ?: 'not specified');

                    if (!$isOnlinePrepay) {
                        $serviceRequestData['partner_assigned_at'] = now();
                        $serviceRequestData['assignment_expires_at'] = now()->addMinutes(5); // 5-minute timeout for testing
                        $serviceRequestData['assignment_timeout_minutes'] = 5;
                    }
                }

                ServiceRequest::create($serviceRequestData);
            }

            // Update order status based on partner selection and payment method.
            // Prepaid online bookings NEVER jump ahead of the payment gateway:
            // they stay in 'created' (unpaid) until the eSewa payment lands and
            // only then become partner_assigned/confirmed (see PaymentService).
            // Otherwise an abandoned booking would surface as "booked" and wake
            // up a partner for money that never arrived.
            if ($preferredPartnerId && $paymentMethod === 'pas') {
                $order->update(['status' => 'partner_assigned']);

                // Log partner assignment for analytics
                \Illuminate\Support\Facades\Log::info('Customer selected partner for booking', [
                    'order_id' => $order->order_id,
                    'customer_id' => $request->user()->user_id,
                    'partner_id' => $preferredPartnerId,
                    'selection_reason' => $partnerSelectionReason,
                    'services' => $requestedServiceIds
                ]);

                // Send immediate notification to directly selected partner
                $this->notifyDirectlyAssignedPartner($order, $preferredPartnerId, $serviceNarration);
            } elseif ($paymentMethod === 'pas') {
                // Orders without a pre-selected partner: PAS orders are confirmed
                // immediately (payment happens at completion)
                $order->update(['status' => 'confirmed']);
            }

            DB::commit();

            AuditService::record($request->user()->user_id, 'booking_created', 'order', $order->order_id, null, [
                'order_id' => $order->order_id,
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'services' => $requestedServiceIds ?? collect($request->services)->pluck('service_id')->toArray(),
            ]);

            // Only PAS bookings are confirmed at creation, so only they get a
            // confirmation push here. Online bookings are unpaid drafts until
            // the gateway lands: the customer is handed the payment WebView
            // right after this, "Payment Successful" is pushed by
            // PaymentService::verifyAndUpdatePayment on success, and abandoned
            // drafts get the expiry-cancel notice from CancelExpiredBookings.
            if ($paymentMethod === 'pas') {
                NotificationService::sendWithPush(
                    $order->user_id,
                    'booking_confirmed',
                    'Booking Confirmed',
                    "Your booking #{$order->order_id} is confirmed. Payment of NPR {$totalAmount} is due after service completion.",
                    ['order_id' => $order->order_id, 'payment_method' => $paymentMethod]
                );
            }

            // Handle response based on payment method
            if ($paymentMethod === 'pas') {
                // PAS orders need no gateway payment; settlement happens at completion.
                return response()->json([
                    'success' => true,
                    'order_id' => $order->order_id,
                    'payment_method' => 'pas',
                    'message' => 'Booking created successfully. Payment will be collected after service completion.',
                    'status' => $order->fresh()->status,
                    'note' => 'For cash collection, commission is deducted from partner balance after customer confirmation.'
                ], 201);
            }

            // For online payments, proceed with eSewa
            // Generate eSewa payment payload. The chargeable amount is the
            // promo-discounted base: amount + tax must equal total_amount.
            $amount = (float) $order->subtotal - (float) $order->discount_amount;
            $taxamount = $order->tax_amount;
            $totalamount = $order->total_amount;

            // Create Payment record
            $payment = \App\Models\Payment::create([
                'booking_id' => $order->order_id,
                'gateway' => 'esewa',
                'amount' => $totalamount,
                'txn_id' => $transactionUuid,
                'status' => 'initiated',
                'expires_at' => now()->addMinutes(15), // 15-minute TTL
            ]);

            $payload = [
                'amount' => $amount,
                'tax_amount' => $taxamount,
                'product_service_charge' => 0,
                'product_delivery_charge' => 0,
                'total_amount' => $totalamount,
                'transaction_uuid' => $transactionUuid,
                'product_code' => config('payment.gateways.esewa.product_code', 'EPAYTEST'),
                'success_url' => url('/api/v1/webhooks/esewa/success'),
                'failure_url' => url('/api/v1/webhooks/esewa/failure'),
                'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            ];

            // HMAC-SHA256 signature
            $message = "total_amount={$payload['total_amount']},transaction_uuid={$payload['transaction_uuid']},product_code={$payload['product_code']}";
            $payload['signature'] = base64_encode(
                hash_hmac('sha256', $message, config('payment.gateways.esewa.secret_key'), true)
            );

            // Return payment initiation response
            return response()->json([
                'success' => true,
                'order_id' => $order->order_id,
                'payment_method' => 'online',
                'payment_url' => rtrim(config('payment.gateways.esewa.form_url') ?? config('payment.gateways.esewa.base_url', 'https://uat.esewa.com.np'), '/') . '/api/epay/main/v2/form',
                'payload' => $payload,
                'message' => 'Booking created successfully. Proceed to payment.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking creation failed', [
                'user_id' => $request->user()?->user_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fire the referral reward hook without ever failing a job
     * completion - rewards are a bonus, settlement is the contract.
     */
    private function creditReferralReward(Order $order): void
    {
        try {
            ReferralService::onOrderCompleted($order);
        } catch (\Throwable $e) {
            Log::warning('Referral reward hook failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function assignPartner(Request $request, $booking): JsonResponse
    {
        // Defense in depth: the route is also protected by the super-admin
        // gate, but never let a customer/partner hit this endpoint directly.
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can assign partners.'
            ], 403);
        }

        $request->validate([
            // partner_profiles is the real table - there is no `partners`
            // table, so `exists:partners,partner_id` always errored out.
            'partner_id' => 'required|exists:partner_profiles,partner_id'
        ]);

        // Lock the order + its requests so two admins (or an admin racing the
        // auto-assign cron) cannot both read status 'pending' and both write an
        // assignment. The status check must happen inside the lock.
        $result = DB::transaction(function () use ($request, $booking) {
            $order = Order::whereKey($booking)->lockForUpdate()->first();

            if (!$order) {
                return ['error' => ['Partner assignment failed', 'Order not found', 404]];
            }

            $serviceRequests = $order->serviceRequests()->lockForUpdate()->get();

            if ($serviceRequests->isEmpty()) {
                return ['error' => ['Service request not found', null, 404]];
            }

            if (!$serviceRequests->contains(fn ($serviceRequest) => $serviceRequest->status === 'pending')) {
                return ['error' => ['Order is not in pending status', null, 400]];
            }

            // Payment gate: never assign a partner to an unpaid online-prepay
            // booking. The auto-assign cron and the preferred-partner path
            // already honour this; the legacy manual endpoint must too.
            if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
                return ['error' => [
                    'Cannot assign a partner until the customer completes online payment.',
                    true,
                    409,
                ]];
            }

            // Manual/admin assignment is an override: no availability or skill
            // filter, every pending request on the order is assigned.
            foreach ($serviceRequests as $serviceRequest) {
                if (in_array($serviceRequest->status, ['completed', 'cancelled'], true)) {
                    continue;
                }

                $serviceRequest->update([
                    'partner_id' => $request->partner_id,
                    'status' => 'assigned',
                    'partner_assigned_at' => now(),
                    'assignment_expires_at' => now()->addMinutes(15),
                    'assignment_timeout_minutes' => 15,
                    'assignment_method' => 'admin_assigned',
                    'assignment_notes' => $request->input('notes', 'Assigned by administrator'),
                    'partner_accepted_at' => null,
                    'partner_rejected_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            $order->update(['status' => 'assigned']);

            return ['order' => $order];
        });

        if (isset($result['error'])) {
            [$message, $paymentRequired, $status] = $result['error'];
            $body = ['success' => false, 'message' => $message];
            if ($paymentRequired !== null) {
                $body['payment_required'] = $paymentRequired;
            }
            return response()->json($body, $status);
        }

        $order = $result['order'];

        $this->notifyPartnerAssignment($order, $request->partner_id, 'admin_assigned');

        // Funnel event, recorded server-side because the client cannot be
        // trusted to know (or report) every assignment (admin + cron paths).
        try {
            AnalyticsEvent::insert([
                'event' => 'partner_assigned',
                'user_id' => $order->user_id,
                'session_id' => null,
                'platform' => 'server',
                'app_version' => null,
                'props' => json_encode([
                    'order_id' => $order->order_id,
                    'partner_id' => $request->partner_id,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('analytics partner_assigned failed', ['order_id' => $order->order_id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Partner assigned successfully',
            'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
        ]);
    }

    /**
     * Get assigned jobs for the authenticated partner
     */
    public function getAssignedJobs(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user has partner profile
        if ($user->user_type !== 'partner') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only partners can view assigned jobs.'
            ], 403);
        }

        // Get partner profile
        $partnerProfile = $user->partnerProfile;
        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $partnerId = $partnerProfile->partner_id;

        // Get orders assigned to this partner with full relationships.
        // Payment gate: online-prepay orders that haven't been paid are not
        // live jobs - a partner must not see (or act on) a booking whose money
        // never arrived.
        $assignedOrders = Order::where(function ($q) {
            $q->where('payment_method', 'pas')
                ->orWhere('payment_status', 'paid');
        })
            ->whereHas('serviceRequests', function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId);
            })
            ->with([
                'items.service',
                'serviceRequests' => function ($query) use ($partnerId) {
                    $query->where('partner_id', $partnerId);
                },
                'serviceRequests.address', // Load address relationship
                'user'  // Customer information
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($assignedOrders),
            'count' => $assignedOrders->count()
        ]);
    }

    public function accept(Request $request, $booking): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        // Lock the request so a double-tap or the assignment-expiry cron racing
        // this accept cannot both act on status 'assigned'. Without the lock
        // the second writer would promote a job the first already moved on.
        $result = DB::transaction(function () use ($booking, $partnerProfile) {
            $order = Order::whereKey($booking)->lockForUpdate()->first();

            if (!$order) {
                return ['error' => ['Unauthorized or service request not found', false, 403]];
            }

            $serviceRequest = $order->serviceRequests()->lockForUpdate()->first();

            if (!$serviceRequest || $serviceRequest->partner_id !== $partnerProfile->partner_id) {
                return ['error' => ['Unauthorized or service request not found', false, 403]];
            }

            // Payment gate: an online-prepay booking is only a real job once the
            // customer's payment has landed. Check this before the status gate
            // so an unpaid preferred-partner request (still 'pending' until the
            // gateway settles) reports the real reason instead of a generic
            // "not assigned" error.
            if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
                return ['error' => [
                    'This job is not booked yet - the customer has not completed payment. You will be notified once the payment is confirmed.',
                    true,
                    409,
                ]];
            }

            if ($serviceRequest->status !== 'assigned') {
                return ['error' => ['Job is not in assigned status and cannot be accepted', false, 400]];
            }

            $serviceRequest->status = 'accepted';
            $serviceRequest->save();
            $order->update(['status' => 'confirmed']);

            return ['order' => $order];
        });

        if (isset($result['error'])) {
            [$message, $paymentRequired, $status] = $result['error'];
            $body = ['success' => false, 'message' => $message];
            if ($paymentRequired) {
                $body['payment_required'] = true;
            }
            return response()->json($body, $status);
        }

        $order = $result['order'];

        return response()->json([
            'success' => true,
            'message' => 'Job accepted successfully',
            'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
        ]);
    }

    public function reject(Request $request, $booking): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $order = Order::findOrFail($booking);
        $serviceRequest = $order->serviceRequests()->first();

        if (!$serviceRequest || $serviceRequest->partner_id !== $partnerProfile->partner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or service request not found'
            ], 403);
        }

        $serviceRequest->status = 'rejected';
        $serviceRequest->partner_id = null;
        $serviceRequest->partner_assigned_at = null;
        $serviceRequest->save();

        $order->update(['status' => 'pending_reassignment']);

        return response()->json([
            'success' => true,
            'message' => 'Job rejected successfully',
            'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
        ]);
    }

    public function startWork(Request $request, $booking): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $order = Order::findOrFail($booking);

        if ($cancelled = $this->cancelledOrderResponse($order)) {
            return $cancelled;
        }

        $serviceRequest = $order->serviceRequests()->first();

        if (!$serviceRequest || $serviceRequest->partner_id !== $partnerProfile->partner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or service request not found'
            ], 403);
        }

        if ($serviceRequest->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Job must be accepted before starting work'
            ], 400);
        }

        // Prepay gate: prepaid orders must be gateway-paid before work starts.
        if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payment must be completed before starting work. Ask the customer to pay the online invoice first.',
                'payment_required' => true,
            ], PaymentRequirementException::HTTP_STATUS);
        }

        $serviceRequest->status = 'working';
        $serviceRequest->save();
        $order->update(['status' => 'in_progress']);

        return response()->json([
            'success' => true,
            'message' => 'Work started successfully',
            'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
        ]);
    }

    public function complete(Request $request, $booking): JsonResponse
    {
        Log::info('=== Booking Completion Request Started ===', [
            'booking_id' => $booking,
            'user_id' => $request->user()->user_id,
            'user_type' => $request->user()->user_type,
            'request_method' => $request->method()
        ]);

        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $order = Order::findOrFail($booking);

        if ($cancelled = $this->cancelledOrderResponse($order)) {
            return $cancelled;
        }

        $serviceRequest = $order->serviceRequests()->first();

        // Partner must be assigned to at least one service request of this order
        $assignedRequests = $order->serviceRequests()->where('partner_id', $partnerProfile->partner_id)->get();

        if ($assignedRequests->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or service request not found'
            ], 403);
        }

        $workableRequests = $assignedRequests->filter(function ($request) {
            return in_array($request->status, ['working', 'in_progress']);
        });

        if ($workableRequests->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Job must be in working status to complete'
            ], 400);
        }

        $serviceRequest = $workableRequests->first();

        // Require completion documents before allowing job completion
        $requiredDocuments = ServiceCompletionDocument::where('service_request_id', $serviceRequest->request_id)
            ->whereIn('document_type', ['signed_form', 'after_photo', 'bill_receipt', 'before_photo', 'other'])
            ->count();

        if ($requiredDocuments < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least one completion document before completing the job',
                'required_documents' => [
                    'signed_form' => 'Customer signed service form',
                    'after_photo' => 'Photo of completed work',
                    'before_photo' => 'Photo of work before starting',
                    'bill_receipt' => 'Service bill or receipt',
                    'other' => 'Other relevant documents'
                ]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Lock the order to serialize concurrent completion attempts (both
            // the per-service-request flow and this legacy endpoint can fire).
            $lockedOrder = Order::whereKey($order->order_id)->lockForUpdate()->first();
            if ($lockedOrder) {
                $order = $lockedOrder;
            }

            // Idempotency: an already-completed order must not be re-settled.
            if ($order->status === 'completed') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This job has already been completed',
                ], 400);
            }

            // Lock only the CALLING partner's workable service request rows -
            // never another partner's work - to serialize concurrent completion.
            $lockedServiceRequests = ServiceRequest::where('order_id', $order->order_id)
                ->where('partner_id', $partnerProfile->partner_id)
                ->whereIn('status', ['working', 'in_progress'])
                ->orderBy('request_id')
                ->lockForUpdate()
                ->get();

            if ($lockedServiceRequests->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Job must be in working status to complete'
                ], 400);
            }

            foreach ($lockedServiceRequests as $workRequest) {
                $workRequest->status = 'completed';
                $workRequest->completed_at = now();
                $workRequest->end_time = now()->format('H:i:s');
                $workRequest->save();
            }

            // Update order status to completed only when no service requests remain
            $pendingRequests = ServiceRequest::where('order_id', $order->order_id)
                ->where('status', '!=', 'completed')
                ->count();

            if ($pendingRequests === 0) {
                $order->update(['status' => 'completed']);

                // Settle payment for the fully completed order (per line).
                $this->settleOrderPayment($order, $request);

                // Credit the referrer if this completion unlocks a referral
                // reward. Never let a reward failure roll back completion.
                $this->creditReferralReward($order);
            }

            AuditService::record($request->user()->user_id, 'job_completed', 'order', $order->order_id, [
                'status' => 'working',
            ], [
                'status' => 'completed',
                'payment_status' => $order->fresh()->payment_status,
            ]);

            NotificationService::sendWithPush(
                $order->user_id,
                'service_completed',
                'Service Completed',
                "Your service for booking #{$order->order_id} has been completed. Please rate your experience!",
                ['order_id' => $order->order_id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job completed successfully',
                'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
            ]);
        } catch (PaymentRequirementException $e) {
            DB::rollBack();
            Log::warning('Job completion blocked by payment requirement', [
                'order_id' => $booking,
                'message' => $e->getMessage(),
            ]);

            // The partner cannot close this job until the customer pays. Tell
            // the customer in their app rather than leaving the job stalled
            // with nobody aware: sendWithPush's 10-minute duplicate rule keeps
            // repeat taps from spamming them.
            $this->notifyCustomerPaymentRequired($order);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'payment_required' => true
            ], $e->statusCode);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Job completion failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete job'
            ], 500);
        }
    }

    /**
     * Nudge the customer when a partner is blocked because the service has not
     * been paid for yet. NotificationService suppresses duplicates within 10
     * minutes, so a partner retrying the completion button cannot spam them.
     */
    private function notifyCustomerPaymentRequired(?Order $order): void
    {
        if (!$order || !$order->user_id) {
            return;
        }

        try {
            NotificationService::sendWithPush(
                $order->user_id,
                'payment_required',
                'Payment due for your booking',
                "Your service provider is waiting to close booking #" . strtoupper(substr($order->order_id, -8)) . ". Open the booking and tap Pay Now.",
                ['order_id' => $order->order_id, 'action' => 'pay']
            );
        } catch (\Exception $e) {
            // A notification failure must never turn a payment block into a 500.
            Log::warning('Payment-required notification failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A cancelled (or refunded) booking must never accept more partner work.
     *
     * Cancellation also cancels the service requests, but a partner client
     * holding stale state - or a direct API call racing the cancellation - could
     * otherwise start/complete the job and flip the order back to in_progress.
     * Returns the error response to send, or null when the order is workable.
     */
    private function cancelledOrderResponse(?Order $order): ?JsonResponse
    {
        if (!$order || !in_array($order->status, [
            'cancelled',
            'cancelled_by_customer',
            'cancelled_by_admin',
            'cancelled_by_partner',
            'refunded',
        ], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'This booking was cancelled and can no longer be worked on.',
            'cancelled' => true,
        ], 409);
    }

    /**
     * Settle payment for a fully completed order (all service requests done).
     *
     * Multi-partner orders are settled per service-request line: each
     * partner is credited based on their OWN line amount (the order item
     * they completed), grouped by partner so a partner with several lines
     * receives a single PARTNER_EARNING entry (the ledger unique index is
     * per booking+user+type).
     *
     * @throws PaymentRequirementException when a payment requirement blocks completion
     */
    private function settleOrderPayment(Order $order, Request $request): void
    {
        $paymentReceived = $request->input('payment_received', 'online');

        // Never trust a bogus value from the client.
        if (!in_array($paymentReceived, ['online', 'cash'])) {
            $paymentReceived = 'online';
        }

        $completedRequests = $order->serviceRequests()
            ->with('orderItem')
            ->where('status', 'completed')
            ->get();

        if ($order->payment_method === 'pas' && $paymentReceived === 'cash') {
            $this->settleCashAtDoor($order, $completedRequests);
            return;
        }

        // online (prepaid) OR pas settled via company QR:
        // the gateway payment must have landed before we credit anything.
        if ($order->payment_status !== 'paid') {
            Log::warning('Completion blocked: gateway payment not received', [
                'order_id' => $order->order_id,
                'payment_method' => $order->payment_method,
                'payment_received' => $paymentReceived,
                'payment_status' => $order->payment_status,
            ]);
            throw new PaymentRequirementException(
                'Customer has not paid yet. They can settle this booking from their app - the booking shows a Pay Now action. Once the payment lands, tap Verify & Complete again.'
            );
        }

        $order->update(['payment_received' => 'online']);

        // Group completed lines by partner and credit each partner only
        // their own lines' share.
        $linesByPartner = $completedRequests->filter(function ($sr) {
            return !empty($sr->partner_id);
        })->groupBy('partner_id');

        if ($linesByPartner->isEmpty()) {
            Log::warning('No partner lines found at settlement', [
                'order_id' => $order->order_id,
                'completed_requests' => $completedRequests->count(),
            ]);
        }

        foreach ($linesByPartner as $partnerId => $partnerLines) {
            // Paid on the order's own line value. The partner-declared
            // "final amount" is no longer a settlement input: on a prepaid
            // booking the customer's payment and the partner's payout must
            // describe the same money.
            $lineSubtotal = (float) $partnerLines->sum(function ($sr) {
                return (float) ($sr->orderItem->total_price ?? 0);
            });

            $serviceId = null;
            $firstWithItem = $partnerLines->first(fn ($sr) => !empty($sr->orderItem?->service_id));
            if ($firstWithItem) {
                $serviceId = $firstWithItem->orderItem->service_id;
            }

            if ($lineSubtotal > 0) {
                $this->creditPartnerShare($order, (int) $partnerId, $lineSubtotal, $serviceId);
            } else {
                Log::warning('Skipping zero line share at settlement', [
                    'order_id' => $order->order_id,
                    'partner_id' => $partnerId,
                ]);
            }
        }
    }

    /**
     * Credit the partner's share (+80% / commission-rule based) once the
     * gateway payment has landed. Never posts twice per order+partner.
     */
    private function creditPartnerShare(Order $order, int $partnerId, float $lineSubtotal, ?int $serviceId): void
    {
        $alreadyCredited = \App\Models\LedgerEntry::where('booking_id', $order->order_id)
            ->where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->exists();

        if ($alreadyCredited) {
            Log::info('Partner share already credited, skipping', [
                'order_id' => $order->order_id,
                'partner_id' => $partnerId,
            ]);
            return;
        }

        $partnerEarning = 0;
        $commissionRule = null;

        if ($serviceId) {
            $commissionRule = \App\Models\CommissionRule::findApplicableRule(
                $serviceId,
                null,
                $lineSubtotal,
                new \DateTime()
            );

            if ($commissionRule) {
                $partnerEarning = $commissionRule->calculateShares($lineSubtotal)['partner_share'];
            }
        }

        if ($partnerEarning <= 0) {
            $partnerEarning = $lineSubtotal * 0.80;
        }

        $ledgerService = app(LedgerService::class);
        $ledgerService->recordPartnerEarning(
            $order->order_id,
            $partnerId,
            $partnerEarning,
            [
                'platform_fee' => max(0, $lineSubtotal - $partnerEarning),
                'commission_rule_used' => $commissionRule ? $commissionRule->rule_id : 'fallback',
                'subtotal' => $lineSubtotal,
                'service_id' => $serviceId,
            ]
        );

        Log::info('Partner share credited on completion', [
            'order_id' => $order->order_id,
            'partner_id' => $partnerId,
            'line_subtotal' => $lineSubtotal,
            'partner_earning' => $partnerEarning,
        ]);
    }

    /**
     * PAS order settled with cash at the door.
     * The partner keeps the cash; nothing is posted to the ledger yet.
     * The customer must confirm the cash handover in the app before the
     * platform commission is deducted (PAS_RECEIPT + PAS_CASH_SETTLEMENT
     * + PAS_COMMISSION are posted on confirmation).
     *
     * Multi-partner orders settle per service-request line: each partner is
     * credited for the lines THEY completed (grouped by partner so the
     * ledger unique index per booking+user+type is respected).
     */
    private function settleCashAtDoor(Order $order, \Illuminate\Support\Collection $completedRequests): void
    {
        // Foul-play guard: if gateway money has landed, cash must NOT also be collected.
        if ($order->payment_status === 'paid') {
            throw new PaymentRequirementException(
                'This booking was already paid online. The customer should not pay cash for it.'
            );
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_received' => 'cash',
            'cash_confirmed' => false,
            'cash_confirmed_at' => null,
            'cash_disputed' => false,
            'cash_disputed_at' => null,
        ]);

        // Invalidate any pending gateway payment sessions so the customer
        // cannot pay online on top of the cash the partner just collected.
        \App\Models\Payment::where('booking_id', $order->order_id)
            ->whereNotIn('status', ['paid', 'refunded'])
            ->update(['status' => 'failed']);

        // Build the ledger at completion: the partner's earnings must show the
        // PAS receipt, cash settlement and commission immediately, without
        // waiting for the customer to tap confirm. Customer confirmation
        // (confirm-cash) is now only a verification step; a dispute reverses
        // these entries via reversePASCashSettlement.
        // One calculator for the whole order (PasSettlementService): the
        // customer's total_amount is split across the completed lines, each
        // partner earns their share of the GROSS service value, and the
        // platform fee / tax / funded discount are posted against the platform
        // account. The partner no longer declares the amount they collected.
        app(\App\Services\PasSettlementService::class)->settle($order);

        NotificationService::sendWithPush(
            $order->user_id,
            'cash_settlement_pending',
            'Confirm your cash payment',
            "You paid NPR {$order->total_amount} in cash for booking #{$order->order_id}. Please confirm this in the app so we can settle the bill.",
            ['order_id' => $order->order_id]
        );
    }

    /**
     * Customer confirms the cash handover for a PAS order.
     * Posts the PAS receipt + cash settlement + platform commission.
     */
    public function confirmCash(Request $request, $booking): JsonResponse
    {
        $order = Order::where('order_id', $booking)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_method !== 'pas' || $order->payment_received !== 'cash') {
            return response()->json(['success' => false, 'message' => 'This order was not settled in cash'], 400);
        }

        if ($order->cash_disputed) {
            return response()->json([
                'success' => false,
                'message' => 'This payment is under dispute. Our support team will contact you shortly.'
            ], 409);
        }

        if ($order->cash_confirmed) {
            return response()->json(['success' => true, 'message' => 'Cash payment already confirmed'], 200);
        }

        DB::beginTransaction();
        try {
            $order = Order::whereKey($order->order_id)->lockForUpdate()->first();

            if ($order->cash_confirmed) {
                DB::rollBack();
                return response()->json(['success' => true, 'message' => 'Cash payment already confirmed'], 200);
            }

            if ($order->cash_disputed) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This payment is under dispute. Our support team will contact you shortly.'
                ], 409);
            }

            $partnerRequest = $order->serviceRequests()->whereNotNull('partner_id')->first();
            $partnerId = $partnerRequest ? $partnerRequest->partner_id : null;

            if (!$partnerId) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No partner is attached to this order. Contact support.'
                ], 409);
            }

            // The PAS ledger was already built at completion by
            // settleCashAtDoor -> recordPASReceipt. Confirm-cash is purely the
            // customer's verification step: mark the handover confirmed and
            // notify the partners. Nothing is re-posted here.
            $order->update([
                'cash_confirmed' => true,
                'cash_confirmed_at' => now(),
            ]);

            $partnerRequests = $order->serviceRequests()->whereNotNull('partner_id')->get()->unique('partner_id');
            foreach ($partnerRequests as $partnerRequest) {
                $partnerProfile = \App\Models\PartnerProfile::find($partnerRequest->partner_id);
                if ($partnerProfile) {
                    NotificationService::sendWithPush(
                        $partnerProfile->partner_id,
                        'cash_settlement_confirmed',
                        'Cash payment confirmed',
                        "The customer confirmed the NPR {$order->total_amount} cash payment for booking #{$order->order_id}. The commission has been deducted.",
                        ['order_id' => $order->order_id]
                    );
                }
            }

            AuditService::record($request->user()->user_id, 'cash_settlement_confirmed', 'order', $order->order_id, null, [
                'order_id' => $order->order_id,
                'partner_ids' => $partnerRequests->pluck('partner_id')->unique()->values()->all(),
                'amount' => $order->total_amount,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash payment confirmed. Thank you!',
                'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cash settlement confirmation failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm cash payment. Please try again.'
            ], 500);
        }
    }

    /**
     * Customer disputes the cash declaration for a PAS order.
     * Flags the order for the admin queue; the partner balance stays untouched.
     */
    public function disputeCash(Request $request, $booking): JsonResponse
    {
        $order = Order::where('order_id', $booking)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_method !== 'pas' || $order->payment_received !== 'cash') {
            return response()->json(['success' => false, 'message' => 'This order was not settled in cash'], 400);
        }

        if ($order->cash_confirmed) {
            return response()->json(['success' => false, 'message' => 'Cash payment already confirmed'], 409);
        }

        DB::beginTransaction();
        try {
            // Lock the order row so a concurrent confirm-cash request cannot
            // race past the dispute (and vice versa): only one outcome wins.
            $order = Order::whereKey($order->order_id)->lockForUpdate()->first();

            if ($order->cash_confirmed) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Cash payment already confirmed'], 409);
            }

            if ($order->cash_disputed) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'This payment is under dispute'], 409);
            }

            $order->update([
                'cash_disputed' => true,
                'cash_disputed_at' => now(),
            ]);

            // Reverse the PAS settlement ledger so the disputed job no longer
            // counts toward the partners' earnings until support resolves it.
            // Multi-partner orders: reverse each partner's line settlement.
            $partnerRequests = $order->serviceRequests()->whereNotNull('partner_id')->get();
            $reversedCount = 0;
            foreach ($partnerRequests as $partnerRequest) {
                try {
                    app(LedgerService::class)->reversePASCashSettlement($order->order_id, $partnerRequest->partner_id);
                    $reversedCount++;
                } catch (\Exception $e) {
                    Log::error('PAS dispute reversal failed', [
                        'order_id' => $order->order_id,
                        'partner_id' => $partnerRequest->partner_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            AuditService::record($request->user()->user_id, 'cash_settlement_disputed', 'order', $order->order_id, null, [
                'order_id' => $order->order_id,
                'reason' => $request->input('reason', 'Not provided'),
                'partners_reversed' => $reversedCount,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cash dispute failed', [
                'order_id' => $order->order_id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to submit dispute. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dispute submitted. Our support team will contact you shortly.',
        ]);
    }

    public function retryPayment(Request $request, $orderId): JsonResponse
    {
        try {
            Log::info('Retry payment attempt', ['order_id' => $orderId, 'user_id' => $request->user()->user_id]);
            $order = Order::where('order_id', $orderId)->firstOrFail();

            // Verify user owns this order
            if ($order->user_id !== $request->user()->user_id) {
                Log::warning('Unauthorized retry payment attempt', ['order_id' => $orderId, 'order_user_id' => $order->user_id, 'request_user_id' => $request->user()->user_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this order'
                ], 403);
            }

            // Check if order is in correct state for payment retry
            if (!in_array($order->payment_status, ['pending', 'failed'])) {
                Log::warning('Invalid payment status for retry', ['order_id' => $orderId, 'payment_status' => $order->payment_status]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order payment cannot be retried. Current status: ' . $order->payment_status
                ], 400);
            }

            // Reset an order that was cancelled by a failed/abandoned payment.
            // This also covers a failure endpoint call and the customer's own
            // cancellation, so a retry from the failure sheet always works.
            if (
                in_array($order->status, ['cancelled', 'cancelled_by_customer'], true)
                && $order->payment_status === 'failed'
            ) {
                $order->update(['status' => 'created']);

                // Restore EVERY service request for the order, not just the
                // first one: multi-service orders were previously left with
                // one cancelled request after a retry. The preferred partner
                // is preserved; PaymentService promotes the request back to
                // 'assigned' once the retried payment settles.
                ServiceRequest::where('order_id', $order->order_id)
                    ->where('status', 'cancelled')
                    ->update([
                        'status' => 'pending',
                        'partner_assigned_at' => null,
                        'assignment_expires_at' => null,
                    ]);
            }

            // Generate new eSewa payment payload with fresh transaction UUID.
            // Normalize to 2 decimals - eSewa validates the signature against
            // the exact total_amount string posted (same rule as the gateway).
            $transactionUuid = (string) Str::uuid();
            $amount = number_format(
                (float) ($order->final_payable ?? $order->total_amount),
                2,
                '.',
                ''
            );

            // Refresh the latest payment session rather than blindly updating
            // the first row: an order can have several historical attempts.
            // The new TTL is mirrored on the order so CancelExpiredBookings
            // cannot cancel the order moments after a retry starts.
            $ttlMinutes = (int) config('payment.payment_timeout_minutes', 15);
            $expiresAt = now()->addMinutes($ttlMinutes);

            $payment = \App\Models\Payment::where('booking_id', $order->order_id)
                ->where('gateway', 'esewa')
                ->latest('id')
                ->first();

            if ($payment) {
                $payment->update([
                    'amount' => $amount,
                    'txn_id' => $transactionUuid,
                    'status' => 'initiated',
                    'expires_at' => $expiresAt,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'retried_at' => now(),
                    ]),
                ]);
            } else {
                $payment = \App\Models\Payment::create([
                    'booking_id' => $order->order_id,
                    'gateway' => 'esewa',
                    'amount' => $amount,
                    'txn_id' => $transactionUuid,
                    'status' => 'initiated',
                    'expires_at' => $expiresAt,
                ]);
            }

            // Sync the order's transaction_uuid with the fresh session so
            // failure/timeout handling (which looks orders up by this column)
            // can never target the previous, superseded session.
            $order->update(['transaction_uuid' => $transactionUuid]);

            $payload = [
                'amount' => $amount,
                'tax_amount' => 0,
                'product_service_charge' => 0,
                'product_delivery_charge' => 0,
                'total_amount' => $amount,
                'transaction_uuid' => $transactionUuid,
                'product_code' => config('payment.gateways.esewa.product_code', 'EPAYTEST'),
                'success_url' => url('/api/v1/webhooks/esewa/success'),
                'failure_url' => url('/api/v1/webhooks/esewa/failure'),
                'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            ];

            // HMAC-SHA256 signature
            $message = "total_amount={$payload['total_amount']},transaction_uuid={$payload['transaction_uuid']},product_code={$payload['product_code']}";
            $payload['signature'] = base64_encode(
                hash_hmac('sha256', $message, config('payment.gateways.esewa.secret_key'), true)
            );

            // Update order with new transaction UUID and reset payment status
            $order->update([
                'transaction_uuid' => $transactionUuid,
                'payment_status' => 'pending',
                'payment_expires_at' => $expiresAt,
            ]);

            // Return fresh payment payload
            return response()->json([
                'success' => true,
                'order_id' => $order->order_id,
                'payment_url' => rtrim(config('payment.gateways.esewa.form_url') ?? config('payment.gateways.esewa.base_url', 'https://uat.esewa.com.np'), '/') . '/api/epay/main/v2/form',
                'payload' => $payload,
                'message' => 'New payment session created. Proceed to payment.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Retry payment failed', ['order_id' => $orderId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create new payment session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a failed/abandoned gateway payment for an online order.
     *
     * eSewa's failure_url frequently reaches the app without a
     * transaction_uuid, and the app intercepts it before the backend webhook
     * can run. Without this endpoint the order stayed alive as an unpaid
     * "booking" (and the partner slot stayed reserved) even though the
     * customer never paid.
     */
    public function markPaymentFailed(Request $request, $orderId): JsonResponse
    {
        $order = Order::where('order_id', $orderId)->firstOrFail();

        if ($order->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this order',
            ], 403);
        }

        if ($order->payment_method !== 'online') {
            return response()->json([
                'success' => false,
                'message' => 'Only online payments can be marked failed.',
            ], 400);
        }

        // Never cancel money that already landed (or a cash-settled job).
        if (
            $order->payment_status === 'paid'
            || $order->payment_received === 'cash'
            || $order->status === 'completed'
        ) {
            return response()->json([
                'success' => true,
                'already_paid' => true,
                'message' => 'Payment is already settled; the booking was left untouched.',
            ]);
        }

        DB::transaction(function () use ($order, $request) {
            $lockedOrder = Order::whereKey($order->order_id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return;
            }

            // Re-check under the lock: a concurrent webhook may have settled
            // the payment between the outer check and this transaction.
            if (
                $lockedOrder->payment_status === 'paid'
                || $lockedOrder->payment_received === 'cash'
                || $lockedOrder->status === 'completed'
            ) {
                return;
            }

            $previousStatus = $lockedOrder->status;

            $lockedOrder->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ]);

            // Cancel every request on the order. Multi-service orders used to
            // leave all but the first request alive on failure.
            ServiceRequest::where('order_id', $lockedOrder->order_id)
                ->whereNotIn('status', ['completed', 'in_progress', 'working'])
                ->update(['status' => 'cancelled']);

            \App\Models\Payment::where('booking_id', $lockedOrder->order_id)
                ->whereIn('status', ['initiated', 'pending'])
                ->update([
                    'status' => 'failed',
                    'metadata' => [
                        'failure_reason' => 'Payment abandoned or declined at gateway',
                        'failed_at' => now(),
                    ],
                ]);

            Log::info('Order cancelled after failed payment', [
                'order_id' => $lockedOrder->order_id,
                'user_id' => $request->user()->user_id,
                'previous_status' => $previousStatus,
            ]);

            AuditService::record($lockedOrder->user_id, 'payment_failed', 'order', $lockedOrder->order_id, [
                'status' => $previousStatus,
                'payment_status' => 'pending',
            ], [
                'status' => 'cancelled',
                'payment_status' => 'failed',
                'reason' => 'Payment abandoned or declined at gateway',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment marked as failed. The booking was not confirmed.',
        ]);
    }

    public function cancel(Request $request, $booking): JsonResponse
    {
        $order = Order::findOrFail($booking);

        // Only allow customer or admin to cancel. `hasRole` does not exist on
        // the User model (roles are the `user_type` enum), so calling it made
        // the admin path a 500 instead of an authorization decision.
        if ($order->user_id !== $request->user()->user_id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this booking'
            ], 403);
        }

        // Check if booking can be cancelled based on current status
        $nonCancellableStatuses = ['in_progress', 'working', 'completed', 'cancelled', 'cancelled_by_customer', 'cancelled_by_partner', 'cancelled_by_admin', 'refunded'];

        if (in_array($order->status, $nonCancellableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel booking in current status: ' . ucfirst(str_replace('_', ' ', $order->status))
            ], 400);
        }

        $result = app(\App\Services\CancellationService::class)->cancel(
            $order,
            'customer',
            $request->input('reason', 'Booking cancelled by customer'),
            $request->user()->user_id
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => new OrderResource($order->load(['items.service', 'serviceRequests']))
        ]);
    }

    /**
     * Customer closes a failed/expired online payment for good.
     *
     * The order is normally already cancelled by the failure/expiry path, so
     * the cancel endpoint answers 400 and the row stayed in "Awaiting Payment"
     * until it aged out. Dismissing marks it hidden instead of pretending to
     * cancel something that is already cancelled - and it still cancels an
     * unpaid order that has not been rolled back yet, so a dismissed attempt
     * can never sit half-alive behind the scenes.
     */
    public function dismissFailedPayment(Request $request, $booking): JsonResponse
    {
        $order = Order::findOrFail($booking);

        if ($order->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to dismiss this payment',
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'A paid booking cannot be dismissed. Cancel the booking instead.',
            ], 400);
        }

        if ($order->payment_method !== 'online') {
            return response()->json([
                'success' => false,
                'message' => 'Only online payments can be dismissed.',
            ], 400);
        }

        if (!$order->status || !str_starts_with($order->status, 'cancelled')) {
            $result = app(\App\Services\CancellationService::class)->cancel(
                $order,
                'customer',
                'Failed payment dismissed by customer',
                $request->user()->user_id
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }
        }

        $order->update(['payment_dismissed_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Failed payment dismissed.',
        ]);
    }

    /**
     * Partner accepts a directly assigned booking
     */
    public function acceptBooking(Request $request, $orderId): JsonResponse
    {
        try {
            $partner = $request->user()->partnerProfile;
            if (!$partner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not found'
                ], 404);
            }

            // Find the service request assigned to this partner
            $serviceRequest = ServiceRequest::where('order_id', $orderId)
                ->where('partner_id', $partner->partner_id)
                ->where('status', 'assigned')
                ->first();

            if (!$serviceRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending assignment found for this booking'
                ], 404);
            }

            // Payment gate: an online-prepay booking is only a real job once
            // the customer's payment has landed. Accepting it any earlier would
            // promote the unpaid order to 'accepted' (= booked) on money that
            // may never arrive.
            $order = Order::find($orderId);
            if ($order && $order->payment_method === 'online' && $order->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'This job is not booked yet - the customer has not completed payment. You will be notified once the payment is confirmed.',
                    'payment_required' => true,
                ], 409);
            }

            DB::beginTransaction();

            // Update service request status
            $serviceRequest->update([
                'status' => 'accepted',
                'partner_accepted_at' => now(),
                'response_notes' => $request->input('notes', 'Booking accepted by partner')
            ]);

            // Update order status
            $order->update(['status' => 'accepted']);

            // Log acceptance
            Log::info('Partner accepted direct assignment', [
                'order_id' => $orderId,
                'partner_id' => $partner->partner_id,
                'accepted_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking accepted successfully',
                'data' => [
                    'order_id' => $orderId,
                    'status' => 'accepted',
                    'accepted_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Partner booking acceptance failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'partner_id' => $request->user()->partnerProfile?->partner_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept booking'
            ], 500);
        }
    }

    /**
     * Partner rejects a directly assigned booking
     */
    public function rejectBooking(Request $request, $orderId): JsonResponse
    {
        try {
            $partner = $request->user()->partnerProfile;
            if (!$partner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not found'
                ], 404);
            }

            // Find the service request assigned to this partner
            $serviceRequest = ServiceRequest::where('order_id', $orderId)
                ->where('partner_id', $partner->partner_id)
                ->where('status', 'assigned')
                ->first();

            if (!$serviceRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending assignment found for this booking'
                ], 404);
            }

            $rejectionReason = $request->input('reason', 'Partner unavailable');

            DB::beginTransaction();

            // Update service request - remove partner assignment
            $serviceRequest->update([
                'status' => 'pending',
                'partner_id' => null,
                'partner_assigned_at' => null,
                'partner_rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
                'assignment_method' => null,
                'assignment_notes' => "Partner rejected assignment. Reason: {$rejectionReason}"
            ]);

            // Update order status to pending_reassignment so the customer
            // can still see the order while a new partner is found
            $order = Order::find($orderId);
            $order->update(['status' => 'pending_reassignment']);

            // Log rejection
            Log::info('Partner rejected direct assignment', [
                'order_id' => $orderId,
                'partner_id' => $partner->partner_id,
                'rejection_reason' => $rejectionReason,
                'rejected_at' => now()
            ]);

            // Trigger reassignment process
            $this->triggerReassignment($order, $rejectionReason);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking rejected. It will be reassigned to another partner.',
                'data' => [
                    'order_id' => $orderId,
                    'status' => 'pending_reassignment',
                    'rejected_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Partner booking rejection failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'partner_id' => $request->user()->partnerProfile?->partner_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject booking'
            ], 500);
        }
    }

    /**
     * Admin reassigns booking to a different partner
     */
    public function reassignBooking(Request $request, $orderId): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can reassign bookings.'
            ], 403);
        }

        $request->validate([
            'partner_id' => 'required|exists:partner_profiles,partner_id',
            'reason' => 'required|string|max:255'
        ]);

        try {
            $newPartnerId = $request->partner_id;
            $reassignmentReason = $request->reason;

            // Admin override: any existing partner can be picked, even if
            // they are not "available" or do not list every service. The
            // admin is expected to have coordinated with the partner.
            $newPartner = \App\Models\PartnerProfile::with('user')
                ->where('partner_id', $newPartnerId)
                ->first();

            if (!$newPartner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected partner does not exist'
                ], 400);
            }

            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Payment gate: an unpaid online-prepay order must never be
            // reassigned - assigning/reassigning promotes it to
            // partner_assigned and wakes up a partner for money that hasn't
            // arrived.
            if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking is unpaid (online prepay). It cannot be assigned to a partner until the customer completes payment. Ask the customer to pay first, or cancel the booking.'
                ], 409);
            }

            DB::beginTransaction();

            $serviceRequests = ServiceRequest::where('order_id', $orderId)
                ->lockForUpdate()
                ->get();

            // Update all service requests for this order
            ServiceRequest::where('order_id', $orderId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->update([
                    'status' => 'assigned',
                    'partner_id' => $newPartnerId,
                    'partner_assigned_at' => now(),
                    'assignment_expires_at' => now()->addMinutes(15),
                    'assignment_timeout_minutes' => 15,
                    'assignment_method' => 'admin_reassigned',
                    'assignment_notes' => "Admin reassignment. Reason: {$reassignmentReason}",
                    'partner_accepted_at' => null,
                    'partner_rejected_at' => null,
                    'rejection_reason' => null,
                ]);

            if ($serviceRequests->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This order has no service requests to reassign'
                ], 400);
            }

            // Update order status
            $order->update(['status' => 'partner_assigned']);

            // Log reassignment
            Log::info('Admin reassigned booking', [
                'order_id' => $orderId,
                'new_partner_id' => $newPartnerId,
                'reassignment_reason' => $reassignmentReason,
                'reassigned_at' => now(),
                'admin_id' => $request->user()->user_id
            ]);

            // Notify new partner about assignment
            $this->notifyPartnerAssignment($order, $newPartnerId, 'admin_reassigned');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking successfully reassigned to new partner',
                'data' => [
                    'order_id' => $orderId,
                    'new_partner_id' => $newPartnerId,
                    'partner_name' => $newPartner->user->full_name,
                    'status' => 'partner_assigned',
                    'reassigned_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin booking reassignment failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'new_partner_id' => $request->partner_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign booking'
            ], 500);
        }
    }

    /**
     * Partner cancels an accepted job (after acceptance but before completion)
     */
    public function cancelAcceptedJob(Request $request, $orderId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $partner = $request->user()->partnerProfile;
            if (!$partner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not found'
                ], 404);
            }

            // Find the service request for this partner
            $serviceRequest = ServiceRequest::where('order_id', $orderId)
                ->where('partner_id', $partner->partner_id)
                ->whereIn('status', ['accepted', 'in_progress', 'working', 'enroute'])
                ->first();

            if (!$serviceRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active job found for this booking'
                ], 404);
            }

            $cancellationReason = $request->reason;

            DB::beginTransaction();

            // Store the old status for logging
            $oldStatus = $serviceRequest->status;

            // Update service request - remove partner assignment
            $serviceRequest->update([
                'status' => 'pending',
                'partner_id' => null,
                'partner_assigned_at' => null,
                'partner_accepted_at' => null,
                'partner_cancelled_at' => now(),
                'cancellation_reason' => $cancellationReason,
                'cancelled_by' => 'partner',
                'assignment_method' => null,
                'assignment_notes' => "Partner cancelled after acceptance. Reason: {$cancellationReason}"
            ]);

            // Update order status back to created for reassignment
            $order = Order::find($orderId);
            $order->update([
                'status' => 'created',
            ]);

            // Log cancellation
            Log::info('Partner cancelled accepted job', [
                'order_id' => $orderId,
                'partner_id' => $partner->partner_id,
                'old_status' => $oldStatus,
                'cancellation_reason' => $cancellationReason,
                'cancelled_at' => now()
            ]);

            // Notify customer about cancellation
            $this->notifyCustomerOfCancellation($order, $partner, $cancellationReason);

            // Trigger automatic reassignment
            $this->triggerReassignment($order, "Partner cancelled: {$cancellationReason}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job cancelled successfully. The booking will be reassigned to another partner.',
                'data' => [
                    'order_id' => $orderId,
                    'status' => 'pending_reassignment',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $cancellationReason
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Partner job cancellation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId,
                'partner_id' => $request->user()->partnerProfile?->partner_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel job. Please try again.'
            ], 500);
        }
    }

    /**
     * Notify customer about partner cancellation
     */
    protected function notifyCustomerOfCancellation($order, $partner, $reason)
    {
        try {
            NotificationService::sendWithPush(
                $order->user_id,
                'booking_cancelled',
                'Partner cancelled your booking',
                "Your partner cancelled booking #{$order->order_id}. Reason: {$reason}. We are finding you a new partner.",
                ['order_id' => $order->order_id, 'reason' => $reason]
            );

            Log::info('Customer notified of cancellation', [
                'order_id' => $order->order_id,
                'customer_id' => $order->user_id,
                'partner_id' => $partner->partner_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify customer of cancellation', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger reassignment process when partner rejects
     */
    private function triggerReassignment($order, $reason)
    {
        Log::info('Reassignment triggered', [
            'order_id' => $order->order_id,
            'reason' => $reason,
            'triggered_at' => now()
        ]);

        NotificationService::sendToAdmins(
            'reassignment',
            'Partner rejected a job',
            "Partner rejected job for order #{$order->order_id}. Reason: {$reason}.",
            ['order_id' => $order->order_id, 'reason' => $reason]
        );

        NotificationService::send(
            $order->user_id,
            'booking_updated',
            'Finding a new partner',
            'Your previous partner rejected the job. We are finding you a new partner.',
            ['order_id' => $order->order_id, 'reason' => $reason]
        );

        AuditService::record(null, 'reassignment_triggered', 'order', $order->order_id, null, [
            'order_id' => $order->order_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Notify partner about new assignment
     */
    private function notifyPartnerAssignment($order, $partnerId, $assignmentType)
    {
        Log::info('Partner assignment notification sent', [
            'order_id' => $order->order_id,
            'partner_id' => $partnerId,
            'assignment_type' => $assignmentType,
            'notified_at' => now()
        ]);

        NotificationService::sendWithPush(
            $partnerId,
            'new_job',
            'New Job Available',
            "A new service job (#{$order->order_id}) has been assigned to you. Check your schedule!",
            ['order_id' => $order->order_id, 'assignment_type' => $assignmentType]
        );
    }

    /**
     * Notify directly assigned partner about new booking
     */
    private function notifyDirectlyAssignedPartner($order, $partnerId, $serviceNarration)
    {
        Log::info('Direct assignment notification sent', [
            'order_id' => $order->order_id,
            'partner_id' => $partnerId,
            'services' => $serviceNarration,
            'notified_at' => now()
        ]);

        NotificationService::sendWithPush(
            $partnerId,
            'new_job',
            'New Job Available',
            "You have been selected for a new job (#{$order->order_id}): {$serviceNarration}. Please accept it within 5 minutes.",
            ['order_id' => $order->order_id, 'services' => $serviceNarration]
        );
    }

    /**
     * Upload completion documents
     */
    public function uploadDocuments(Request $request, $serviceRequestId): JsonResponse
    {
        Log::info('=== Document Upload Request Started ===', [
            'service_request_id' => $serviceRequestId,
            'partner_id' => $request->user()->id,
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'request_size' => strlen($request->getContent()),
            'has_files' => $request->hasFile('documents'),
            'all_request_keys' => array_keys($request->all()),
        ]);

        try {
            // Check if service request exists - can be uploaded by customer or partner
            $user = $request->user();

            Log::info('Upload Documents - User Info', [
                'user_id' => $user->user_id,
                'user_type' => $user->user_type ?? 'unknown'
            ]);

            // Try to find service request by partner first
            $serviceRequest = ServiceRequest::with('order')
                ->where('request_id', $serviceRequestId)
                ->where('partner_id', $user->user_id)
                ->first();

            // If not found as partner, try to find as customer through order
            if (!$serviceRequest) {
                $serviceRequest = ServiceRequest::with('order')
                    ->where('request_id', $serviceRequestId)
                    ->whereHas('order', function ($orderQuery) use ($user) {
                        $orderQuery->where('user_id', $user->user_id);
                    })
                    ->first();
            }

            Log::info('Upload Documents - Service Request Lookup Result', [
                'service_request_id' => $serviceRequestId,
                'user_id' => $user->user_id,
                'found' => !!$serviceRequest,
                'partner_id' => $serviceRequest ? $serviceRequest->partner_id : null,
                'status' => $serviceRequest ? $serviceRequest->status : 'not_found',
                'order_user_id' => $serviceRequest && $serviceRequest->order ? $serviceRequest->order->user_id : null
            ]);

            Log::info('Service request lookup', [
                'service_request_id' => $serviceRequestId,
                'user_id' => $user->user_id,
                'user_type' => $user->user_type,
                'found' => !!$serviceRequest,
                'status' => $serviceRequest ? $serviceRequest->status : 'not_found',
                'partner_id' => $serviceRequest ? $serviceRequest->partner_id : null,
                'order_user_id' => $serviceRequest && $serviceRequest->order ? $serviceRequest->order->user_id : null
            ]);

            if (!$serviceRequest) {
                Log::warning('Service request not found', [
                    'service_request_id' => $serviceRequestId,
                    'user_id' => $user->user_id,
                    'user_type' => $user->user_type
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Service request not found or not in progress'
                ], 404);
            }

            // Allow upload for multiple statuses (in_progress, working)
            $allowedStatuses = ['in_progress', 'working'];
            if (!in_array($serviceRequest->status, $allowedStatuses)) {
                Log::warning('Service request not in allowed status', [
                    'service_request_id' => $serviceRequestId,
                    'current_status' => $serviceRequest->status,
                    'allowed_statuses' => $allowedStatuses
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Service request not found or not in progress'
                ], 404);
            }

            Log::info('Request validation data', [
                'has_documents' => $request->hasFile('documents'),
                'documents_count' => $request->hasFile('documents') ? count($request->file('documents')) : 0,
                'has_document_types' => $request->has('document_types'),
                'document_types' => $request->input('document_types'),
                'completion_notes' => $request->input('completion_notes'),
                'final_amount' => $request->input('final_amount'),
                'all_files' => $request->allFiles(),
            ]);

            // Check if this is a base64 upload (alternative for React Native)
            if ($request->has('documents_base64')) {
                Log::info('Processing base64 file upload');

                // Validate base64 upload format
                $validator = Validator::make($request->all(), [
                    'documents_base64' => 'required|array|min:1|max:5',
                    'documents_base64.*.name' => 'required|string',
                    'documents_base64.*.type' => 'required|string',
                    'documents_base64.*.data' => 'required|string',
                    'document_types' => 'required|array',
                    'document_types.*' => 'required|in:signed_form,bill_receipt,before_photo,after_photo,other',
                    'completion_notes' => 'nullable|string|max:1000',
                    'final_amount' => 'nullable|numeric|min:0|max:999999.99'
                ]);

                if ($validator->fails()) {
                    Log::warning('Base64 validation failed', [
                        'service_request_id' => $serviceRequestId,
                        'validation_errors' => $validator->errors()->toArray(),
                        'request_data' => array_keys($request->all())
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return $this->handleBase64Upload($request, $serviceRequest);
            }

            // Standard FormData upload validation
            $validator = Validator::make($request->all(), [
                'documents' => 'required|array|min:1|max:5',
                'documents.*' => 'required|file|max:10240', // 10MB max per file
                'document_types' => 'required|array',
                'document_types.*' => 'required|in:signed_form,bill_receipt,before_photo,after_photo,other',
                'completion_notes' => 'nullable|string|max:1000',
                'final_amount' => 'nullable|numeric|min:0|max:999999.99'
            ]);

            if ($validator->fails()) {
                Log::warning('FormData validation failed', [
                    'service_request_id' => $serviceRequestId,
                    'validation_errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('FormData validation passed, starting file processing');

            $uploadedDocuments = [];
            $documents = $request->file('documents');
            $documentTypes = $request->input('document_types');

            DB::beginTransaction();

            foreach ($documents as $index => $document) {
                // Validate file type
                if (!in_array($document->getMimeType(), $this->allowedMimeTypes)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Unsupported file type: {$document->getMimeType()}"
                    ], 422);
                }

                // Validate file size
                if ($document->getSize() > $this->maxFileSize) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'File size exceeds maximum limit of 10MB'
                    ], 422);
                }

                // Generate unique filename
                $extension = $document->getClientOriginalExtension();
                $filename = Str::uuid() . '.' . $extension;

                // Store file
                $path = $document->storeAs(
                    'service-completion/' . $serviceRequestId,
                    $filename,
                    'private'
                );

                if (!$path) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload document'
                    ], 500);
                }

                // Calculate file hash for integrity
                $fileContent = file_get_contents($document->getPathname());
                $fileHash = hash('sha256', $fileContent);

                // Create document record
                $completionDocument = ServiceCompletionDocument::create([
                    'service_request_id' => $serviceRequest->request_id,
                    'partner_id' => $serviceRequest->partner_id,
                    'document_type' => $documentTypes[$index] ?? 'other',
                    'original_filename' => $document->getClientOriginalName(),
                    'stored_filename' => $filename,
                    'file_path' => $path,
                    'file_size' => $document->getSize(),
                    'mime_type' => $document->getMimeType(),
                    'file_hash' => $fileHash,
                    'access_token' => Str::random(64),
                    'uploaded_by' => $request->user()->user_id
                ]);

                $uploadedDocuments[] = [
                    'id' => $completionDocument->document_id,
                    'type' => $completionDocument->document_type,
                    'filename' => $completionDocument->original_filename,
                    'size' => $completionDocument->file_size,
                    'uploaded_at' => $completionDocument->created_at
                ];
            }

            // Update service request with completion notes and final amount
            $updateData = [];
            if ($request->filled('completion_notes')) {
                $updateData['completion_notes'] = $request->input('completion_notes');
            }
            if ($request->filled('final_amount')) {
                $updateData['final_amount'] = $request->input('final_amount');
            }

            if (!empty($updateData)) {
                $serviceRequest->update($updateData);
            }

            DB::commit();

            Log::info('Documents uploaded successfully', [
                'service_request_id' => $serviceRequestId,
                'partner_id' => $request->user()->id,
                'uploaded_documents_count' => count($uploadedDocuments),
                'document_paths' => array_column($uploadedDocuments, 'file_path')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully',
                'documents' => $uploadedDocuments,
                'service_request' => $serviceRequest->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Document upload failed', [
                'service_request_id' => $serviceRequestId,
                'partner_id' => $request->user()->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete service with commission handling
     */
    public function completeService(Request $request, $serviceRequestId): JsonResponse
    {
        Log::info('=== Service Completion Request Started ===', [
            'service_request_id' => $serviceRequestId,
            'user_id' => $request->user()->user_id,
            'user_type' => $request->user()->user_type,
            'request_method' => $request->method(),
            'request_keys' => array_keys($request->all())
        ]);

        try {
            $serviceRequest = ServiceRequest::with([
                'order',
                'order.orderItems.service',
                'partner.user'
            ])->where('request_id', $serviceRequestId)
                ->where(function ($query) use ($request) {
                    $query->where('partner_id', $request->user()->user_id)
                        ->orWhereHas('order', function ($orderQuery) use ($request) {
                            $orderQuery->where('user_id', $request->user()->user_id);
                        });
                })
                ->whereIn('status', ['in_progress', 'working'])
                ->first();

            Log::info('Service request lookup for completion', [
                'service_request_id' => $serviceRequestId,
                'user_id' => $request->user()->user_id,
                'found' => !!$serviceRequest,
                'status' => $serviceRequest ? $serviceRequest->status : 'not_found',
                'partner_id' => $serviceRequest ? $serviceRequest->partner_id : null,
                'order_user_id' => $serviceRequest && $serviceRequest->order ? $serviceRequest->order->user_id : null
            ]);

            if (!$serviceRequest) {
                Log::warning('Service request not found for completion', [
                    'service_request_id' => $serviceRequestId,
                    'user_id' => $request->user()->user_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Service request not found or not in correct status for completion'
                ], 404);
            }

            $order = $serviceRequest->order;

            if ($cancelled = $this->cancelledOrderResponse($order)) {
                return $cancelled;
            }

            // Check what documents are uploaded
            $uploadedDocuments = ServiceCompletionDocument::where('service_request_id', $serviceRequest->request_id)
                ->get(['document_type', 'original_filename'])
                ->toArray();

            Log::info('Checking completion documents', [
                'service_request_id' => $serviceRequest->request_id,
                'uploaded_documents' => $uploadedDocuments,
                'uploaded_count' => count($uploadedDocuments)
            ]);

            // Ensure required documents are uploaded
            $requiredDocuments = ServiceCompletionDocument::where('service_request_id', $serviceRequest->request_id)
                ->whereIn('document_type', ['signed_form', 'after_photo', 'bill_receipt', 'before_photo', 'other'])
                ->count();

            Log::info('Document validation result', [
                'required_documents_count' => $requiredDocuments,
                'minimum_required' => 1
            ]);

            if ($requiredDocuments < 1) {
                Log::warning('Insufficient documents for completion', [
                    'service_request_id' => $serviceRequest->request_id,
                    'uploaded_documents' => $uploadedDocuments,
                    'required_count' => $requiredDocuments
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Please upload at least one completion document before completing the service',
                    'required_documents' => [
                        'signed_form' => 'Customer signed service form',
                        'after_photo' => 'Photo of completed work',
                        'before_photo' => 'Photo of work before starting',
                        'bill_receipt' => 'Service bill or receipt',
                        'other' => 'Other relevant documents'
                    ],
                    'uploaded_documents' => $uploadedDocuments,
                    'upload_endpoint' => "/api/v1/service-completion/{$serviceRequestId}/upload-documents"
                ], 422);
            }

            DB::beginTransaction();

            // Lock the service request row to serialize concurrent completion requests
            $lockedServiceRequest = ServiceRequest::where('request_id', $serviceRequest->request_id)
                ->whereIn('status', ['in_progress', 'working'])
                ->lockForUpdate()
                ->first();

            if (!$lockedServiceRequest) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Service request not found or not in correct status for completion'
                ], 404);
            }

            $serviceRequest = $lockedServiceRequest;

            // Lock the order row too: two concurrent completions of different
            // service requests in the SAME order must serialize, otherwise
            // both could observe "zero pending requests" and settle the
            // payment twice (double partner earning, double ledger).
            $lockedOrder = Order::where('order_id', $order->order_id)->lockForUpdate()->first();
            if ($lockedOrder) {
                $order = $lockedOrder;
            }

            // Update service request status
            $serviceRequest->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Update overall order status if all service requests are completed
            $pendingRequests = ServiceRequest::where('order_id', $order->order_id)
                ->where('status', '!=', 'completed')
                ->count();

            Log::info('Checking overall order completion', [
                'order_id' => $order->order_id,
                'pending_requests' => $pendingRequests,
                'current_order_status' => $order->status
            ]);

            if ($pendingRequests === 0) {
                $orderUpdateResult = $order->update(['status' => 'completed']);
                Log::info('Updated overall order status', [
                    'order_id' => $order->order_id,
                    'update_result' => $orderUpdateResult,
                    'new_status' => $order->fresh()->status
                ]);

                // Settle the payment only once the entire order is completed.
                // - online / pas+QR: requires gateway 'paid', then credits each
                //   partner their OWN service-request line share (multi-partner
                //   orders are split per SR line amount).
                // - pas+cash:        requires NO gateway money, marks cash pending confirmation.
                $this->settleOrderPayment($order, $request);

                // Credit the referrer if this completion unlocks a referral
                // reward. Never let a reward failure roll back completion.
                $this->creditReferralReward($order);
            }

            AuditService::record($request->user()->user_id, 'job_completed', 'service_request', $serviceRequest->request_id, [
                'status' => $serviceRequest->getOriginal('status'),
            ], [
                'status' => 'completed',
            ]);

            NotificationService::sendWithPush(
                $order->user_id,
                'service_completed',
                'Service Completed',
                "Your service for booking #{$order->order_id} has been completed. Please rate your experience!",
                ['order_id' => $order->order_id]
            );

            DB::commit();

            // Verify the updates actually happened
            $freshOrder = $order->fresh();
            Log::info('Final order state after commit', [
                'order_id' => $freshOrder->order_id,
                'status' => $freshOrder->status,
                'payment_status' => $freshOrder->payment_status,
                'payment_method' => $freshOrder->payment_method,
                'commission_deducted' => $freshOrder->commission_deducted,
                'commission_deducted_at' => $freshOrder->commission_deducted_at
            ]);

            $response = [
                'success' => true,
                'message' => 'Service completed successfully',
                'service_request' => $serviceRequest->fresh()
            ];

            // Add PAS-specific message
            if ($order->payment_method === 'pas') {
                $response['payment_note'] = $order->payment_received === 'cash'
                    ? 'The customer has been asked to confirm the cash payment. Commission is deducted after confirmation.'
                    : 'Payment received online. Your share has been added to your balance.';
            }

            return response()->json($response);
        } catch (PaymentRequirementException $e) {
            DB::rollBack();
            Log::warning('Service completion blocked by payment requirement', [
                'order_id' => $order->order_id ?? null,
                'message' => $e->getMessage(),
            ]);

            // Same reason as complete(): the customer has to know the job is
            // waiting on their payment, otherwise nothing moves.
            $this->notifyCustomerPaymentRequired($order);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'payment_required' => true
            ], $e->statusCode);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completion documents for a service request
     */
    public function getDocuments(Request $request, $serviceRequestId): JsonResponse
    {
        try {
            $serviceRequest = ServiceRequest::where('request_id', $serviceRequestId)
                ->where('partner_id', $request->user()->user_id)
                ->first();

            if (!$serviceRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service request not found'
                ], 404);
            }

            $documents = ServiceCompletionDocument::where('service_request_id', $serviceRequestId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->document_id,
                        'type' => $doc->document_type,
                        'filename' => $doc->original_filename,
                        'size' => $doc->file_size,
                        'uploaded_at' => $doc->created_at,
                        'download_url' => route('api.service-completion.download-document', [
                            'documentId' => $doc->document_id,
                            'accessToken' => base64_encode($doc->document_id . ':' . $doc->file_hash)
                        ])
                    ];
                });

            return response()->json([
                'success' => true,
                'documents' => $documents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download completion document with secure access
     */
    public function downloadDocument(Request $request, $documentId, $accessToken)
    {
        Log::info('Document download request', [
            'document_id' => $documentId,
            'access_token' => $accessToken,
            'user_id' => $request->user()->user_id ?? 'guest'
        ]);

        try {
            $document = ServiceCompletionDocument::where('document_id', $documentId)->first();

            if (!$document) {
                Log::warning('Document not found', ['document_id' => $documentId]);
                abort(404, 'Document not found');
            }

            Log::info('Document found', [
                'document_id' => $documentId,
                'file_path' => $document->file_path,
                'original_filename' => $document->original_filename,
                'file_hash' => $document->file_hash
            ]);

            // Verify access token
            $expectedToken = base64_encode($document->document_id . ':' . $document->file_hash);
            if ($accessToken !== $expectedToken) {
                Log::warning('Invalid access token', [
                    'document_id' => $documentId,
                    'provided_token' => $accessToken,
                    'expected_token' => $expectedToken
                ]);
                abort(403, 'Invalid access token');
            }

            // Check if file exists in private storage first, then public storage
            $filePath = $document->file_path;
            $privateFullPath = storage_path('app/private/' . $filePath);
            $publicFullPath = storage_path('app/public/' . $filePath);

            Log::info('Checking file existence', [
                'file_path' => $filePath,
                'private_path' => $privateFullPath,
                'public_path' => $publicFullPath,
                'exists_private_storage' => Storage::disk('private')->exists($filePath),
                'exists_public_storage' => Storage::disk('public')->exists($filePath),
                'exists_private_filesystem' => file_exists($privateFullPath),
                'exists_public_filesystem' => file_exists($publicFullPath)
            ]);

            $finalPath = null;
            if (Storage::disk('private')->exists($filePath) || file_exists($privateFullPath)) {
                $finalPath = file_exists($privateFullPath) ? $privateFullPath : Storage::disk('private')->path($filePath);
            } elseif (Storage::disk('public')->exists($filePath) || file_exists($publicFullPath)) {
                $finalPath = file_exists($publicFullPath) ? $publicFullPath : Storage::disk('public')->path($filePath);
            }

            if (!$finalPath) {
                Log::error('File not found in any storage location', [
                    'document_id' => $documentId,
                    'file_path' => $filePath,
                    'private_path' => $privateFullPath,
                    'public_path' => $publicFullPath
                ]);
                abort(404, 'File not found in storage');
            }

            // Update access tracking
            $document->increment('access_count');
            $document->update(['last_accessed_at' => now()]);

            Log::info('Serving file download', [
                'document_id' => $documentId,
                'final_path' => $finalPath,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type
            ]);

            // Return file with proper headers
            return response()->download(
                $finalPath,
                $document->original_filename,
                [
                    'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Document download failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download document: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleBase64Upload(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $documentsBase64 = $request->input('documents_base64', []);
            $documentTypes = $request->input('document_types', []);
            $completionNotes = $request->input('completion_notes');
            $finalAmount = $request->input('final_amount');

            Log::info('Base64 upload data', [
                'documents_count' => count($documentsBase64),
                'document_types_count' => count($documentTypes),
                'has_completion_notes' => !empty($completionNotes),
                'has_final_amount' => !empty($finalAmount)
            ]);

            if (empty($documentsBase64) || count($documentsBase64) !== count($documentTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documents and document types count mismatch'
                ], 422);
            }

            $uploadedDocuments = [];
            $updateData = [];

            DB::beginTransaction();

            foreach ($documentsBase64 as $index => $docData) {
                // Decode base64 file
                $fileData = base64_decode($docData['data']);
                if ($fileData === false) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Invalid base64 data for document {$index}"
                    ], 422);
                }

                // Generate unique filename
                $extension = $this->getExtensionFromMimeType($docData['type']);
                $filename = 'service_completion_' . $serviceRequest->request_id . '_' . time() . '_' . $index . '.' . $extension;
                $filePath = 'service_completion_documents/' . $filename;

                Log::info('Base64 Upload - File Processing', [
                    'index' => $index,
                    'original_name' => $docData['name'],
                    'mime_type' => $docData['type'],
                    'extension' => $extension,
                    'generated_filename' => $filename,
                    'file_path' => $filePath,
                    'service_request_id' => $serviceRequest->request_id,
                    'partner_id' => $serviceRequest->partner_id
                ]);

                // Store file
                if (!Storage::disk('public')->put($filePath, $fileData)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Failed to store document {$index}"
                    ], 500);
                }

                // Calculate file hash for integrity
                $fileHash = hash('sha256', $fileData);

                // Create document record
                $document = ServiceCompletionDocument::create([
                    'service_request_id' => $serviceRequest->request_id,
                    'partner_id' => $serviceRequest->partner_id,
                    'document_type' => $documentTypes[$index],
                    'original_filename' => $docData['name'],
                    'stored_filename' => $filename,
                    'file_path' => $filePath,
                    'mime_type' => $docData['type'],
                    'file_size' => strlen($fileData),
                    'file_hash' => $fileHash,
                    'access_token' => Str::random(64),
                    'uploaded_by' => $request->user()->user_id,
                ]);

                $uploadedDocuments[] = $document;

                Log::info('Document stored', [
                    'document_id' => $document->id,
                    'file_path' => $filePath,
                    'file_size' => strlen($fileData)
                ]);
            }

            // Update service request with completion data
            if (!empty($completionNotes)) {
                $updateData['completion_notes'] = $completionNotes;
            }

            if (!empty($finalAmount)) {
                $updateData['final_amount'] = $finalAmount;
            }

            if (!empty($updateData)) {
                $serviceRequest->update($updateData);
            }

            DB::commit();

            Log::info('Base64 documents uploaded successfully', [
                'service_request_id' => $serviceRequest->request_id,
                'uploaded_documents_count' => count($uploadedDocuments)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documents uploaded successfully',
                'documents' => $uploadedDocuments,
                'service_request' => $serviceRequest->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Base64 upload failed', [
                'service_request_id' => $serviceRequest->request_id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload documents: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'image' => 'jpg', // fallback for React Native generic "image" type
        ];

        return $extensions[$mimeType] ?? 'jpg';
    }
}
