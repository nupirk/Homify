<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\StatusEnumHelper;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): View
    {
        $query = Order::with(['user', 'orderItems.service', 'serviceRequests.partner.user']);

        // Set default filters if no filters are applied
        $hasFilters = $request->hasAny(['search', 'status', 'payment_status', 'date_range', 'partner_id']);

        if (!$hasFilters) {
            // Default: Show paid orders with confirmed and cancelled_by_partner statuses
            $request->merge([
                'payment_status' => 'paid',
                'status' => ['confirmed', 'cancelled_by_partner']
            ]);
        }

        // Enhanced search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Date range filtering
        if ($request->filled('date_range')) {
            $dateRange = $request->get('date_range');
            switch ($dateRange) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                case 'custom':
                    if ($request->filled(['date_from', 'date_to'])) {
                        $query->whereBetween('created_at', [
                            $request->get('date_from') . ' 00:00:00',
                            $request->get('date_to') . ' 23:59:59'
                        ]);
                    }
                    break;
            }
        }

        // Multiple status filtering
        if ($request->filled('status')) {
            $statuses = is_array($request->get('status')) ? $request->get('status') : [$request->get('status')];
            $query->whereIn('status', $statuses);
        }

        // Payment status filtering
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Partner filtering
        if ($request->filled('partner_id')) {
            $partnerId = $request->get('partner_id');
            if ($partnerId === 'unassigned') {
                $query->whereDoesntHave('serviceRequests.partner');
            } else {
                // partner_profiles has no user_id column: partner_id is the
                // shared user PK, so filter on it directly. Filtering by
                // user_id threw an SQL error whenever the admin used this
                // dropdown.
                $query->whereHas('serviceRequests.partner', function ($q) use ($partnerId) {
                    $q->where('partner_profiles.partner_id', $partnerId);
                });
            }
        }

        // Service type filtering
        if ($request->filled('service_id')) {
            $serviceId = $request->get('service_id');
            if ($serviceId === 'multiple') {
                // Filter for orders with multiple services
                $query->has('orderItems', '>', 1);
            } else {
                // Filter for specific service
                $query->whereHas('orderItems', function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId);
                });
            }
        }

        // Assignment status filtering
        if ($request->filled('assignment_status')) {
            $assignmentStatus = $request->get('assignment_status');
            switch ($assignmentStatus) {
                case 'fully_assigned':
                    $query->whereDoesntHave('serviceRequests', function ($q) {
                        $q->whereNull('partner_id');
                    });
                    break;
                case 'partially_assigned':
                    $query->whereHas('serviceRequests', function ($q) {
                        $q->whereNotNull('partner_id');
                    })->whereHas('serviceRequests', function ($q) {
                        $q->whereNull('partner_id');
                    });
                    break;
                case 'unassigned':
                    $query->whereDoesntHave('serviceRequests', function ($q) {
                        $q->whereNotNull('partner_id');
                    });
                    break;
            }
        }

        // Service filtering
        if ($request->filled('service')) {
            $query->whereHas('orderItems', function ($q) use ($request) {
                $q->where('service_id', $request->get('service'));
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $orders = $query->paginate(20)->withQueryString();

        // Get additional data for filters
        $services = Service::where('is_active', true)->get();

        // Enhanced partner loading with skills, availability, and current workload.
        // Availability comes from AvailabilityService, the single source of
        // truth: a partner with no explicit rows is bookable on the default
        // 09:00-18:00 window, so counting availability rows marked every
        // newly approved partner as "Unavailable" in the admin UI.
        $availabilityService = app(\App\Services\AvailabilityService::class);

        $partners = \App\Models\PartnerProfile::with([
            'user',
            'services',
        ])
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->map(function ($partner) use ($availabilityService) {
                // Workload uses the same blocking statuses as the availability
                // engine (assigned jobs occupy the calendar too).
                $activeOrders = \App\Models\ServiceRequest::where('partner_id', $partner->partner_id)
                    ->whereIn('status', \App\Services\AvailabilityService::BLOCKING_STATUSES)
                    ->count();

                $partner->is_busy = $activeOrders >= 3; // Consider busy if 3+ active orders
                $partner->current_workload = $activeOrders;
                $partner->availability_status = $availabilityService->dayStatus($partner->partner_id, now());
                $partner->is_available_today = $partner->availability_status !== 'unavailable';

                return $partner;
            });

        // Calculate comprehensive statistics
        $stats = $this->calculateOrderStatistics();

        return view('admin.orders.index', compact('orders', 'services', 'partners', 'stats'));
    }

    /**
     * Calculate comprehensive order statistics
     */
    private function calculateOrderStatistics(): array
    {
        $now = now();

        // Basic counts using proper enum values
        $totalOrders = Order::count();
        $ordersToday = Order::whereDate('created_at', today())->count();
        $completedOrders = Order::where('status', StatusEnumHelper::ORDER_STATUS_COMPLETED)->count();
        $pendingOrders = Order::whereIn('status', [
            StatusEnumHelper::ORDER_STATUS_CREATED,
            StatusEnumHelper::ORDER_STATUS_PENDING,
            StatusEnumHelper::ORDER_STATUS_PENDING_PAYMENT,
            StatusEnumHelper::ORDER_STATUS_AWAITING_PARTNER
        ])->count();

        // Dynamic status counts - get all possible status values from the database
        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Dynamic payment status counts
        $paymentStatusCounts = Order::selectRaw('payment_status, COUNT(*) as count')
            ->whereNotNull('payment_status')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();

        // Revenue calculations using proper enum values
        $completedStatuses = [
            StatusEnumHelper::ORDER_STATUS_COMPLETED,
            StatusEnumHelper::ORDER_STATUS_CLOSED
        ];
        $totalRevenue = Order::whereIn('status', $completedStatuses)->sum('total_amount');
        $revenueThisMonth = Order::whereIn('status', $completedStatuses)
            ->whereBetween('created_at', [$now->startOfMonth()->copy(), $now->endOfMonth()->copy()])
            ->sum('total_amount');
        $revenueToday = Order::whereIn('status', $completedStatuses)
            ->whereDate('created_at', today())
            ->sum('total_amount');

        // Average order value (last 30 days)
        $avgOrderValue = Order::where('created_at', '>=', $now->subDays(30))
            ->avg('total_amount') ?? 0;

        // Growth rate calculation (this month vs last month)
        $lastMonthOrders = Order::whereBetween('created_at', [
            $now->copy()->subMonth()->startOfMonth(),
            $now->copy()->subMonth()->endOfMonth()
        ])->count();
        $thisMonthOrders = Order::whereBetween('created_at', [
            $now->startOfMonth()->copy(),
            $now->endOfMonth()->copy()
        ])->count();
        $growthRate = $lastMonthOrders > 0 ? (($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 : 0;

        // Completion rate
        $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

        // Payment statistics
        $totalPayments = Order::whereNotNull('payment_status')->count();
        $successfulPayments = Order::whereIn('payment_status', ['captured', 'authorized', 'completed', 'success', 'paid'])->count();
        $failedPayments = Order::where('payment_status', 'failed')->count();
        $paymentSuccessRate = $totalPayments > 0 ? ($successfulPayments / $totalPayments) * 100 : 0;

        // Partner statistics
        $activePartners = DB::table('partner_profiles')
            ->where('status', 'active')
            ->count();

        $unassignedOrders = Order::whereDoesntHave('serviceRequests.partner')->count();

        // Partners online (based on recent session activity)
        $partnersOnline = DB::table('partner_profiles')
            ->join('users', 'partner_profiles.partner_id', '=', 'users.user_id')
            ->join('sessions', 'users.user_id', '=', 'sessions.user_id')
            ->where('partner_profiles.status', 'active')
            ->where('sessions.last_activity', '>=', $now->subMinutes(15)->timestamp)
            ->distinct('partner_profiles.partner_id')
            ->count();

        // Partner acceptance rate (orders assigned to partners vs orders accepted by partners)
        $assignedOrders = Order::whereIn('status', [StatusEnumHelper::ORDER_STATUS_PARTNER_ASSIGNED])->count();
        $acceptedOrderStatuses = [
            StatusEnumHelper::ORDER_STATUS_ACCEPTED,
            StatusEnumHelper::ORDER_STATUS_CONFIRMED,
            StatusEnumHelper::ORDER_STATUS_ENROUTE,
            StatusEnumHelper::ORDER_STATUS_IN_PROGRESS,
            StatusEnumHelper::ORDER_STATUS_WORKING,
            StatusEnumHelper::ORDER_STATUS_COMPLETED
        ];
        $acceptedOrders = Order::whereIn('status', $acceptedOrderStatuses)->count();
        $partnerAcceptanceRate = $assignedOrders > 0 ? ($acceptedOrders / ($assignedOrders + $acceptedOrders)) * 100 : 0;

        // Merge basic stats with dynamic counts
        $stats = [
            'total_orders' => $totalOrders,
            'orders_today' => $ordersToday,
            'completed_orders' => $completedOrders,
            'pending_orders' => $pendingOrders,
            'total_revenue' => $totalRevenue,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_today' => $revenueToday,
            'avg_order_value' => $avgOrderValue,
            'growth_rate' => $growthRate,
            'completion_rate' => $completionRate,
            'payment_success_rate' => $paymentSuccessRate,
            'failed_payments' => $failedPayments,
            'status_counts' => $statusCounts,
            'payment_status_counts' => $paymentStatusCounts,
            'active_partners' => $activePartners,
            'unassigned_orders' => $unassignedOrders,
            'partners_online' => $partnersOnline,
            'partner_acceptance_rate' => $partnerAcceptanceRate,
            'avg_response_time' => 12, // Placeholder - would need additional tracking
        ];

        // Add individual status counts for easier access
        foreach ($statusCounts as $status => $count) {
            $stats["status_{$status}"] = $count;
        }

        // Add individual payment status counts for easier access
        foreach ($paymentStatusCounts as $status => $count) {
            $stats["payment_{$status}"] = $count;
        }

        return $stats;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:users,user_id',
            'service_id' => 'required|exists:services,service_id',
            'quantity' => 'required|integer|min:1',
            'service_address' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Get service details
            $service = Service::find($validated['service_id']);
            $subtotal = $service->base_price * $validated['quantity'];
            $discountAmount = $validated['discount_amount'] ?? 0;
            $taxAmount = ($subtotal - $discountAmount) * 0.1; // 10% tax
            $totalAmount = $subtotal - $discountAmount + $taxAmount;

            // Create order
            $order = Order::create([
                'user_id' => $validated['customer_id'],
                'status' => 'created',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'admin_notes' => $validated['admin_notes']
            ]);

            // Create order item
            $order->orderItems()->create([
                'service_id' => $validated['service_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $service->base_price,
                'total_price' => $subtotal
            ]);

            // Create service request
            $serviceRequest = $order->serviceRequests()->create([
                'service_date' => $validated['scheduled_at'] ? \Carbon\Carbon::parse($validated['scheduled_at'])->toDateString() : null,
                'start_time' => $validated['scheduled_at'] ? \Carbon\Carbon::parse($validated['scheduled_at'])->toTimeString() : null,
                'service_address' => $validated['service_address'],
                'status' => 'pending',
                'special_instructions' => $validated['admin_notes']
            ]);

            DB::commit();

            return redirect()->route('admin.orders.index')
                ->with('success', 'Order created successfully! Order ID: #' . substr($order->order_id, 0, 8));
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create order: ' . $e->getMessage()]);
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order)
    {
        // Get valid order statuses from the StatusEnumHelper
        $validOrderStatuses = implode(',', \App\Helpers\StatusEnumHelper::getOrderStatusValues());

        $request->validate([
            'status' => 'required|in:' . $validOrderStatuses,
            'notes' => 'nullable|string|max:500'
        ]);

        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Add admin notes if provided
        if ($request->notes) {
            $order->update([
                'status' => $newStatus,
                'admin_notes' => ($order->admin_notes ? $order->admin_notes . "\n\n" : '') .
                    "Status changed to {$newStatus}: " . $request->notes
            ]);
        } else {
            $order->update(['status' => $newStatus]);
        }

        // Update related service request status if needed
        if ($serviceRequest = $order->serviceRequests->first()) {
            // Use the StatusEnumHelper to map order status to service request status
            // See app/Helpers/StatusEnumHelper.php for the full mapping logic
            $serviceRequestStatus = \App\Helpers\StatusEnumHelper::mapOrderStatusToServiceRequestStatus($newStatus);
            $serviceRequest->update(['status' => $serviceRequestStatus]);
        }

        // Log the status change
        Log::info("Order {$order->order_id} status changed from {$oldStatus} to {$newStatus} by admin " . Auth::id(), [
            'notes' => $request->notes
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully'
            ]);
        }

        return redirect()->back()->with('success', 'Order status updated successfully');
    }

    /**
     * Bulk update order status
     */
    public function bulkUpdateStatus(Request $request)
    {
        // Get valid order statuses from the StatusEnumHelper
        $validOrderStatuses = implode(',', \App\Helpers\StatusEnumHelper::getOrderStatusValues());

        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,order_id',
            'status' => 'required|in:' . $validOrderStatuses
        ]);

        $orderIds = $request->order_ids;
        $status = $request->status;

        try {
            DB::beginTransaction();

            Order::whereIn('order_id', $orderIds)->update(['status' => $status]);

            // Update related service requests using the StatusEnumHelper
            DB::table('service_requests')
                ->whereIn('order_id', $orderIds)
                ->update([
                    'status' => \App\Helpers\StatusEnumHelper::mapOrderStatusToServiceRequestStatus($status)
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($orderIds) . ' orders updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to update orders: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(string $id): View
    {
        $order = Order::with([
            'user',
            'orderItems.service',
            'serviceRequests.partner.user',
            'serviceRequests.orderItem.service', // Load service through orderItem for each serviceRequest
            'serviceRequests.address',
            'serviceRequests.completionDocuments' // Add completion documents
        ])->findOrFail($id);

        // Get all service IDs from the order for enhanced partner matching
        $serviceIds = $order->orderItems->pluck('service_id')->unique()->values();

        // Admin override: every active partner is an assignable target. The
        // old query kept only partners who listed one of the order's services,
        // so a specific partner could not be picked unless they already had the
        // skill - "direct partner selection" was impossible. Everyone is listed
        // now; matching + available partners simply rank first.
        $availability = app(\App\Services\AvailabilityService::class);
        $duration = $availability->durationForServices($serviceIds->all());

        $firstRequest = $order->serviceRequests->first();
        $availabilityDate = $firstRequest?->service_date
            ? \Carbon\Carbon::parse($firstRequest->service_date)
            : \Carbon\Carbon::today();
        $requestedStart = $firstRequest?->start_time
            ? $availabilityDate->copy()->setTimeFromTimeString($firstRequest->start_time->format('H:i'))
            : null;

        // The order's own requests must not count as conflicts against the
        // partner being assigned: without this, the currently assigned partner
        // looked "Busy" on their own job and every reassignment showed the
        // incumbent as unavailable.
        $ignoreRequestIds = $order->serviceRequests->pluck('request_id')->all();

        $partners = \App\Models\PartnerProfile::with([
            'user',
            'services',
        ])
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->map(function ($partner) use ($serviceIds, $availability, $availabilityDate, $requestedStart, $duration, $ignoreRequestIds) {
                // Calculate compatibility score
                $partnerServiceIds = $partner->services->pluck('service_id');
                $compatibility = $serviceIds->count() > 0
                    ? $partnerServiceIds->intersect($serviceIds)->count() / $serviceIds->count() * 100
                    : 0;

                // Workload must use the same blocking statuses as availability
                // math, otherwise "Busy" and the calendar disagree.
                $activeOrders = \App\Models\ServiceRequest::where('partner_id', $partner->partner_id)
                    ->whereIn('status', \App\Services\AvailabilityService::BLOCKING_STATUSES)
                    ->count();

                // Availability is calculated for the ORDER's date/time, not
                // "today". A partner free today is not necessarily free on the
                // booked slot, and vice versa. The reason for a failure matters:
                // a slot in the past is not the same as a partner being booked.
                $availabilityStatus = $this->partnerAvailabilityFor(
                    $availability,
                    $partner->partner_id,
                    $requestedStart,
                    $availabilityDate,
                    $duration,
                    $ignoreRequestIds
                );

                // Add calculated fields to partner object
                $partner->compatibility_score = round($compatibility, 1);
                $partner->matches_services = $compatibility > 0;
                $partner->is_busy = $activeOrders >= 3;
                $partner->current_workload = $activeOrders;
                $partner->availability_status = $availabilityStatus;
                $partner->is_available_today = in_array($availabilityStatus, ['available', 'limited'], true);
                $partner->priority_score = $this->calculatePartnerPriority(
                    $partner,
                    $activeOrders,
                    $compatibility,
                    $partner->is_available_today
                );

                return $partner;
            })
            ->sortByDesc('priority_score')
            ->values();

        // Group service requests by order item for better display
        $serviceRequestsByOrderItem = [];
        foreach ($order->serviceRequests as $serviceRequest) {
            if ($serviceRequest->orderItem) {
                $serviceRequestsByOrderItem[$serviceRequest->orderItem->item_id] = $serviceRequest;
            }
        }

        return view('admin.orders.show', compact('order', 'partners', 'serviceRequestsByOrderItem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, string $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if (in_array($order->status, ['completed', 'closed', 'cancelled_by_admin', 'cancelled_by_customer', 'cancelled_by_partner', 'refunded'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel an order that is already completed or cancelled.'
                ], 400);
            }
            return back()->withErrors(['error' => 'Cannot cancel an order that is already completed or cancelled.']);
        }

        try {
            $result = app(\App\Services\CancellationService::class)->cancel(
                $order,
                'admin',
                $request->reason,
                Auth::id()
            );

            if (!$result['success']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                    ], 400);
                }
                return back()->withErrors(['error' => $result['message']]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            }

            return redirect()->route('admin.orders.show', $order->order_id)
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel order: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to cancel order: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign partner to order
     */
    public function assignPartner(Request $request, string $id)
    {
        $order = Order::findOrFail($id);

        // The admin UI (modal + order page) submits a partner_profiles id.
        // Validating against users.user_id silently rejected every real
        // partner, so assignment from the dashboard could never succeed.
        $request->validate([
            'partner_id' => [
                'required',
                'exists:partner_profiles,partner_id',
            ],
            'notes' => 'nullable|string|max:500'
        ]);

        $allowedStatuses = [
            StatusEnumHelper::ORDER_STATUS_AWAITING_PARTNER,
            StatusEnumHelper::ORDER_STATUS_CREATED,
            StatusEnumHelper::ORDER_STATUS_PENDING,
            StatusEnumHelper::ORDER_STATUS_CONFIRMED,
            StatusEnumHelper::ORDER_STATUS_PARTNER_ASSIGNED
        ];

        if (!in_array($order->status, $allowedStatuses)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Can only assign/reassign partners to orders that are awaiting partner assignment or already have a partner assigned. Current status: {$order->status}"
                ], 400);
            }
            return back()->withErrors(['error' => "Can only assign/reassign partners to orders that are awaiting partner assignment or already have a partner assigned. Current status: {$order->status}"]);
        }

        // Payment gate: an unpaid online-prepay order must never be assigned -
        // assigning promotes it to partner_assigned and notifies a partner for
        // money that hasn't arrived. PAS orders (settled at the door) and paid
        // online orders are assignable.
        if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
            $message = "This booking is unpaid (online prepay) and cannot be assigned to a partner yet. The customer must complete the eSewa payment first (payment_status: {$order->payment_status}).";
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 409);
            }
            return back()->withErrors(['error' => $message]);
        }

        try {
            DB::beginTransaction();

            $isReassignment = false;

            // Admin override: assign every pending service request on the order
            // to the picked partner, regardless of matching skills or
            // availability. Expiry + method are recorded so the partner app and
            // the timeout handler can act on a real assignment.
            $serviceRequests = $order->serviceRequests()->lockForUpdate()->get();

            if ($serviceRequests->isEmpty()) {
                DB::rollBack();

                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'This order has no service requests to assign'], 404);
                }

                return back()->withErrors(['error' => 'This order has no service requests to assign']);
            }

            // A reassignment means a partner was already on the job, even when
            // the order status is still confirmed/created (the old check only
            // looked at `partner_assigned`, so those reassignments were logged
            // and announced as fresh assignments).
            $isReassignment = $serviceRequests->contains(function ($serviceRequest) {
                return !empty($serviceRequest->partner_id)
                    && !in_array($serviceRequest->status, ['completed', 'cancelled'], true);
            });

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
                    'assignment_method' => $isReassignment ? 'admin_reassigned' : 'admin_assigned',
                    'assignment_notes' => $request->notes,
                    'partner_accepted_at' => null,
                    'partner_rejected_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            // The order is now (re)assigned. Only skip the write when it is
            // already in that state.
            $statusUpdated = $order->status !== 'partner_assigned';
            if ($statusUpdated) {
                $order->update(['status' => 'partner_assigned']);
            }

            // Log the assignment/reassignment
            $action = $isReassignment ? 'reassigned' : 'assigned';
            Log::info("Partner {$action} to order {$order->order_id}", [
                'order_id' => $order->order_id,
                'partner_id' => $request->partner_id,
                'notes' => $request->notes,
                'admin_id' => Auth::id()
            ]);

            DB::commit();

            // Wake the partner up: notification is sent after commit so the
            // assignment row is guaranteed to exist when the app polls.
            try {
                \App\Services\NotificationService::sendWithPush(
                    (int) $request->partner_id,
                    'new_job',
                    'New Job Available',
                    "A new service job (#{$order->order_id}) has been assigned to you. Please accept it within 15 minutes.",
                    ['order_id' => $order->order_id, 'assignment_type' => $isReassignment ? 'admin_reassigned' : 'admin_assigned']
                );
            } catch (\Throwable $e) {
                Log::warning('Admin assignment notification failed', ['order_id' => $order->order_id]);
            }

            $action = $isReassignment ? 'reassigned' : 'assigned';
            $statusMessage = $statusUpdated ? ' Order status updated to partner assigned.' : '';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Partner {$action} successfully.{$statusMessage}"
                ]);
            }

            return redirect()->route('admin.orders.show', $order->order_id)
                ->with('success', "Partner {$action} successfully.{$statusMessage}");
        } catch (\Exception $e) {
            DB::rollback();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign partner: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to assign partner: ' . $e->getMessage()]);
        }
    }

    /**
     * Export orders to CSV
     */
    public function export(Request $request)
    {
        $query = Order::with(['user', 'orderItems.service', 'serviceRequests.partner.user']);

        // Apply filters similar to index method
        if ($request->filled('status')) {
            $query->whereIn('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->whereIn('payment_status', $request->payment_status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        // Cursor, not get(): the previous version pulled every matching order
        // and its relations into memory before writing a single row.
        $orders = $query->orderBy('created_at', 'desc')->cursor();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders_export_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Order ID',
                'Customer Name',
                'Customer Email',
                'Status',
                'Payment Status',
                'Total Amount',
                'Service',
                'Partner',
                'Created At',
                'Updated At'
            ]);

            // CSV data
            $rowCount = 0;
            $totalAmount = 0.0;

            foreach ($orders as $order) {
                $rowCount++;
                $totalAmount += (float) $order->total_amount;

                $service = $order->orderItems->first()?->service;
                $partner = $order->serviceRequests->first()?->partner;

                fputcsv($file, [
                    $order->order_id,
                    $order->user?->name ?? 'Unknown',
                    $order->user->email,
                    ucfirst(str_replace('_', ' ', $order->status)),
                    ucfirst(str_replace('_', ' ', $order->payment_status)),
                    'Rs ' . $order->total_amount,
                    $service?->service_name ?? 'N/A',
                    $partner?->user->name ?? 'Unassigned',
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->updated_at->format('Y-m-d H:i:s')
                ]);
            }

            fputcsv($file, [
                'TOTAL',
                $rowCount . ' orders',
                '',
                '',
                '',
                'Rs ' . number_format($totalAmount, 2),
                '',
                '',
                '',
                ''
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Mark an order as completed.
     */
    public function complete(string $id): RedirectResponse
    {
        $order = Order::findOrFail($id);

        $order->update(['status' => 'completed']);
        $order->serviceRequests()->update(['status' => 'completed']);

        return redirect()->route('admin.orders.show', $order->order_id)
            ->with('success', 'Order marked as completed.');
    }

    /**
     * Get order statistics.
     */
    public function statistics()
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', StatusEnumHelper::ORDER_STATUS_PENDING)->count(),
            'confirmed_orders' => Order::where('status', StatusEnumHelper::ORDER_STATUS_CONFIRMED)->count(),
            'completed_orders' => Order::where('status', StatusEnumHelper::ORDER_STATUS_COMPLETED)->count(),
            'cancelled_orders' => Order::where('status', StatusEnumHelper::ORDER_STATUS_CANCELLED)->count(),
            'total_revenue' => Order::where('status', StatusEnumHelper::ORDER_STATUS_COMPLETED)->sum('total_amount'),
        ];

        return response()->json($stats);
    }

    /**
     * Get suitable partners for an order based on skills and availability
     */
    public function getSuitablePartners($orderId)
    {
        try {
            $order = Order::with('orderItems.service')->findOrFail($orderId);

            // Get all service IDs from the order
            $serviceIds = $order->orderItems->pluck('service_id')->unique();

            // Debug: Log the order and service IDs
            Log::info("Getting suitable partners for order: {$orderId}, services: " . $serviceIds->implode(','));

            // Every active partner is a valid manual-assignment target: the
            // admin is the override. Partners who cover the order's services
            // are ranked first, the rest stay in the list instead of
            // disappearing (the old query hid anyone without a matching skill,
            // which made "assign any partner" impossible).
            $availability = app(\App\Services\AvailabilityService::class);
            $firstRequest = $order->serviceRequests()->orderBy('request_id')->first();
            $availabilityDate = $firstRequest?->service_date
                ? \Carbon\Carbon::parse($firstRequest->service_date)
                : \Carbon\Carbon::today();
            $requestedStart = $firstRequest?->start_time
                ? $availabilityDate->copy()->setTimeFromTimeString($firstRequest->start_time->format('H:i'))
                : null;
            // The order's own requests are the job being assigned, not
            // conflicting bookings - ignore them for every partner.
            $ignoreRequestIds = $order->serviceRequests()->pluck('request_id')->all();
            $duration = $availability->durationForServices($serviceIds->all());

            $suitablePartners = \App\Models\PartnerProfile::with([
                'user',
                'services',
            ])
                ->where('status', 'active')
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->get()
                ->map(function ($partner) use ($serviceIds, $availability, $availabilityDate, $requestedStart, $duration, $ignoreRequestIds) {
                    // Calculate compatibility score
                    $partnerServiceIds = $partner->services->pluck('service_id');
                    $compatibility = $serviceIds->count() > 0
                        ? $partnerServiceIds->intersect($serviceIds)->count() / $serviceIds->count() * 100
                        : 0;

                    // Workload uses the same blocking statuses as the
                    // availability engine (assigned jobs occupy the calendar).
                    $activeOrders = \App\Models\ServiceRequest::where('partner_id', $partner->partner_id)
                        ->whereIn('status', \App\Services\AvailabilityService::BLOCKING_STATUSES)
                        ->count();

                    $availabilityStatus = $this->partnerAvailabilityFor(
                        $availability,
                        $partner->partner_id,
                        $requestedStart,
                        $availabilityDate,
                        $duration,
                        $ignoreRequestIds
                    );
                    $isAvailable = in_array($availabilityStatus, ['available', 'limited'], true);
                    $isBusy = $activeOrders >= 3;

                    // Return structured data compatible with existing JavaScript expectations
                    return [
                        'partner_id' => $partner->partner_id,
                        'user' => [
                            'name' => $partner->user->name ?? 'Unknown Partner',
                            'full_name' => $partner->user->full_name ?? ($partner->user->name ?? 'Unknown Partner'),
                            'email' => $partner->user->email ?? 'N/A',
                            'phone' => $partner->user->phone ?? 'N/A',
                        ],
                        'status' => $partner->status,
                        'rating_avg' => $partner->rating_avg ?? 0,
                        'total_ratings' => $partner->total_ratings ?? 0,
                        'compatibility_score' => round($compatibility, 1),
                        'matches_services' => $compatibility > 0,
                        'is_busy' => $isBusy,
                        'current_workload' => $activeOrders,
                        'is_available_today' => $isAvailable,
                        'priority_score' => $this->calculatePartnerPriority($partner, $activeOrders, $compatibility, $isAvailable),
                        'services' => $partner->services->map(function ($service) {
                            return [
                                'service_id' => $service->service_id,
                                'name' => $service->name,
                                'base_price' => $service->pivot->base_price ?? null
                            ];
                        })->toArray(),
                        'availability_status' => $availabilityStatus
                    ];
                })
                ->sortByDesc('priority_score')
                ->values();

            Log::info("Found " . $suitablePartners->count() . " suitable partners");

            return response()->json($suitablePartners);
        } catch (\Exception $e) {
            Log::error("Error getting suitable partners: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load partners: ' . $e->getMessage(),
                'partners' => []
            ], 500);
        }
    }

    /**
     * Slot-level availability label for the admin assignment UI.
     *
     * Returns `available`, `limited`, `busy` (another job overlaps this slot),
     * `past` (the booked slot has already passed) or `unavailable` (outside the
     * partner's working window / blocked window). This is informational only -
     * an admin may always assign any partner as an override.
     */
    private function partnerAvailabilityFor(
        \App\Services\AvailabilityService $availability,
        int $partnerId,
        ?\Carbon\Carbon $requestedStart,
        \Carbon\Carbon $date,
        int $duration,
        array $ignoreRequestIds = []
    ): string {
        if (!$requestedStart) {
            return $availability->dayStatus($partnerId, $date, null, $duration, $ignoreRequestIds);
        }

        $check = $availability->check($partnerId, $requestedStart, $duration, $ignoreRequestIds);

        if ($check['available']) {
            return $availability->statusFor($partnerId, $requestedStart, $duration, $ignoreRequestIds);
        }

        return match ($check['reason']) {
            'past' => 'past',
            'conflict' => 'busy',
            'outside_hours' => 'outside hours',
            default => 'unavailable',
        };
    }

    /**
     * Calculate partner priority score for assignment
     */
    private function calculatePartnerPriority($partner, $workload, $compatibility, ?bool $isAvailable = null)
    {
        $score = 0;

        // Base compatibility score (0-100)
        $score += $compatibility;

        // Rating bonus (0-50)
        $score += ($partner->rating_avg ?? 3) * 10;

        // Availability bonus (0-20)
        $isAvailable ??= $partner->availability->count() > 0;
        if ($isAvailable) {
            $score += 20;
        }

        // Workload penalty (0 to -30)
        $score -= $workload * 10;

        // Busy penalty
        if ($workload >= 3) {
            $score -= 20;
        }

        return $score;
    }

    /**
     * Download completion document (Admin access)
     */
    public function downloadCompletionDocument(Request $request, $documentId)
    {
        \Illuminate\Support\Facades\Log::info('Admin document download attempt', [
            'admin_id' => Auth::id(),
            'document_id' => $documentId,
            'request_method' => $request->method(),
            'route_params' => $request->route()->parameters()
        ]);

        try {
            // Find document by primary key (document_id)
            $document = \App\Models\ServiceCompletionDocument::where('document_id', $documentId)->first();

            if (!$document) {
                \Illuminate\Support\Facades\Log::warning('Document not found for admin download', [
                    'document_id' => $documentId,
                    'admin_id' => Auth::id()
                ]);
                abort(404, 'Document not found');
            }

            \Illuminate\Support\Facades\Log::info('Document found for admin download', [
                'document_id' => $documentId,
                'file_path' => $document->file_path,
                'original_filename' => $document->original_filename,
                'service_request_id' => $document->service_request_id
            ]);

            // Verify admin has access to this document through the order
            $serviceRequest = $document->serviceRequest;
            if (!$serviceRequest) {
                \Illuminate\Support\Facades\Log::error('Service request not found for document', [
                    'document_id' => $documentId,
                    'admin_id' => Auth::id()
                ]);
                abort(404, 'Service request not found');
            }

            // Check if file exists in private storage first, then public storage
            $filePath = $document->file_path;
            $privateFullPath = storage_path('app/private/' . $filePath);
            $publicFullPath = storage_path('app/public/' . $filePath);

            \Illuminate\Support\Facades\Log::info('Checking file paths for admin download', [
                'document_id' => $documentId,
                'file_path' => $filePath,
                'private_path' => $privateFullPath,
                'public_path' => $publicFullPath,
                'exists_private_storage' => \Illuminate\Support\Facades\Storage::disk('private')->exists($filePath),
                'exists_public_storage' => \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath),
                'exists_private_filesystem' => file_exists($privateFullPath),
                'exists_public_filesystem' => file_exists($publicFullPath)
            ]);

            $finalPath = null;
            if (\Illuminate\Support\Facades\Storage::disk('private')->exists($filePath) || file_exists($privateFullPath)) {
                $finalPath = file_exists($privateFullPath) ? $privateFullPath : \Illuminate\Support\Facades\Storage::disk('private')->path($filePath);
            } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath) || file_exists($publicFullPath)) {
                $finalPath = file_exists($publicFullPath) ? $publicFullPath : \Illuminate\Support\Facades\Storage::disk('public')->path($filePath);
            }

            if (!$finalPath) {
                \Illuminate\Support\Facades\Log::error('Admin download - File not found in any storage location', [
                    'document_id' => $documentId,
                    'file_path' => $filePath,
                    'private_path' => $privateFullPath,
                    'public_path' => $publicFullPath,
                    'admin_id' => Auth::id()
                ]);
                abort(404, 'File not found in storage');
            }            // Log access for audit
            \Illuminate\Support\Facades\Log::info('Admin document access successful', [
                'admin_id' => Auth::id(),
                'document_id' => $documentId,
                'service_request_id' => $serviceRequest->request_id,
                'order_id' => $serviceRequest->order_id
            ]);

            // Update access count and last accessed time
            $document->increment('access_count');
            $document->update(['last_accessed_at' => now()]);

            \Illuminate\Support\Facades\Log::info('Serving admin file download', [
                'document_id' => $documentId,
                'final_path' => $finalPath,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type
            ]);

            // Return file download
            return response()->download(
                $finalPath,
                $document->original_filename,
                [
                    'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin document download failed', [
                'admin_id' => Auth::id(),
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download document: ' . $e->getMessage(),
                'error_code' => 'DOWNLOAD_FAILED'
            ], 500);
        }
    }

    /**
     * Get completion documents for a service request (Admin access)
     */
    public function getCompletionDocuments(Request $request, $serviceRequestId): JsonResponse
    {
        try {
            $serviceRequest = \App\Models\ServiceRequest::findOrFail($serviceRequestId);

            $documents = $serviceRequest->completionDocuments()
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->document_id,
                        'type' => $doc->document_type,
                        'filename' => $doc->original_filename,
                        'size' => $doc->file_size,
                        'human_size' => $doc->human_file_size,
                        'uploaded_at' => $doc->created_at,
                        'is_verified' => $doc->is_verified,
                        'verified_at' => $doc->verified_at,
                        'download_url' => route('admin.orders.download-document', ['documentId' => $doc->document_id])
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
     * Verify completion document (Admin access)
     */
    public function verifyDocument(Request $request, $documentId): JsonResponse
    {
        try {
            $document = \App\Models\ServiceCompletionDocument::findOrFail($documentId);

            // Mark document as verified
            $document->markAsVerified(Auth::id());

            \Illuminate\Support\Facades\Log::info('Document verified by admin', [
                'admin_id' => Auth::id(),
                'document_id' => $documentId,
                'service_request_id' => $document->service_request_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document verified successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Document verification failed', [
                'admin_id' => Auth::id(),
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
