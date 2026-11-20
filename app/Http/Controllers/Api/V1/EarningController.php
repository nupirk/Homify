<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use App\Models\PartnerPayout;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EarningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $partnerId = $request->user()->partnerProfile?->partner_id;

            if (!$partnerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not found'
                ], 404);
            }

            // Get partner balance summary from cache
            $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

            // Get partner earnings from ledger_entries (includes PAS transactions)
            $earningsQuery = LedgerEntry::where('user_id', $partnerId)
                ->whereIn('type', [
                    'PARTNER_EARNING',    // Online payment earnings
                    'PAS_RECEIPT',        // PAS cash payment received from customer
                    'PAS_CASH_SETTLEMENT',// PAS cash collected share (removed from balance)
                    'PAS_COMMISSION',     // PAS platform commission deduction
                    'WITHDRAWAL'          // Payout processed
                ])
                ->with(['order.orderItems.service'])
                ->orderBy('created_at', 'desc');

            // Apply date filters if provided
            if ($request->filled('date_from')) {
                $earningsQuery->where('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $earningsQuery->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $earnings = $earningsQuery->paginate(10);

            // For each earning entry, calculate standardized breakdown using PricingService
            $earnings->getCollection()->transform(function ($earning) {
                try {
                    // PAS cash jobs: the partner should see what the customer
                    // actually paid and what the platform kept, instead of a
                    // settlement line that looks like a deduction out of
                    // nowhere. A promo never reduces the partner's share.
                    if ($earning->booking_id && $earning->type === 'PARTNER_EARNING'
                        && (($earning->metadata['payment'] ?? null) === 'pas')) {
                        // A fully discounted job has no cash leg at all, so the
                        // split must come from the settlement metadata rather
                        // than from the existence of a settlement row.
                        $settlement = LedgerEntry::where('booking_id', $earning->booking_id)
                            ->where('user_id', $earning->user_id)
                            ->where('type', 'PAS_CASH_SETTLEMENT')
                            ->orderBy('id')
                            ->first();

                        $collected = $settlement ? abs((float) $settlement->amount) : 0.0;
                        $earned = (float) $earning->amount;
                        $gross = (float) ($earning->metadata['gross_service_value'] ?? 0);

                        $earning->payment_mode = 'pas';
                        $earning->cash_collected = $collected;
                        // Negative means the platform owes the partner (a job
                        // the promo covered in full).
                        $earning->platform_cut = round($collected - $earned, 2);
                        $earning->breakdown = [
                            'base_price' => $gross,
                            'tax_amount' => 0,
                            'platform_fee' => round($gross - $earned, 2),
                            'partner_earning' => $earned,
                            'commission_rate' => $gross > 0 ? round((($gross - $earned) / $gross) * 100, 1) : 0,
                            'validation_status' => 'pas_settlement',
                            'rates_source' => 'pas_settlement',
                        ];
                    }

                    if ($earning->booking_id && ($earning->payment_mode ?? null) !== 'pas') {
                        // Get the customer payment and tax from ledger entries
                        $customerPayment = LedgerEntry::where('booking_id', $earning->booking_id)
                            ->where('type', 'CUSTOMER_DEBIT')
                            ->first();

                        $taxPayable = LedgerEntry::where('booking_id', $earning->booking_id)
                            ->where('type', 'TAX_PAYABLE')
                            ->first();

                        $customerAmount = $customerPayment ? abs($customerPayment->amount) : 0;
                        $taxAmount = $taxPayable ? abs($taxPayable->amount) : 0;

                        if ($customerAmount > 0) {
                            // Use PricingService for standardized calculation
                            $serviceCategory = $earning->order?->orderItems?->first()?->service?->category?->name ?? 'default';
                            $city = $earning->order?->user?->city ?? 'kathmandu';

                            $breakdown = PricingService::calculateEarningsBreakdown(
                                $customerAmount,
                                $serviceCategory,
                                $city
                            );

                            // Validate the breakdown
                            if (PricingService::validateEarningsBreakdown($breakdown)) {
                                $earning->breakdown = [
                                    'base_price' => $breakdown['base_price'],
                                    'tax_amount' => $breakdown['tax_amount'],
                                    'platform_fee' => $breakdown['platform_fee'],
                                    'partner_earning' => $breakdown['partner_earning'],
                                    'commission_rate' => $breakdown['commission_rate'],
                                    'validation_status' => 'valid',
                                    'rates_source' => $breakdown['rates_source'],
                                ];
                            } else {
                                // Fallback calculation if validation fails
                                $basePrice = $customerAmount - $taxAmount;
                                $platformFee = max(0, $basePrice - $earning->amount);

                                $earning->breakdown = [
                                    'base_price' => $basePrice,
                                    'tax_amount' => $taxAmount,
                                    'platform_fee' => $platformFee,
                                    'partner_earning' => $earning->amount,
                                    'commission_rate' => $basePrice > 0 ? round(($platformFee / $basePrice) * 100, 1) : 0,
                                    'validation_status' => 'fallback',
                                    'rates_source' => 'legacy_calculation',
                                ];
                            }
                        } else {
                            // No customer payment found, use earning amount as fallback
                            $earning->breakdown = [
                                'base_price' => $earning->amount,
                                'tax_amount' => 0,
                                'platform_fee' => 0,
                                'partner_earning' => $earning->amount,
                                'commission_rate' => 0,
                                'validation_status' => 'incomplete_data',
                                'rates_source' => 'fallback',
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // If breakdown calculation fails, add default breakdown
                    $earning->breakdown = [
                        'base_price' => $earning->amount,
                        'tax_amount' => 0,
                        'platform_fee' => 0,
                        'partner_earning' => $earning->amount,
                        'commission_rate' => 0,
                        'validation_status' => 'error',
                        'rates_source' => 'error_fallback',
                        'error_message' => $e->getMessage(),
                    ];
                }
                return $earning;
            });

            // Calculate real-time stats from ledger (includes all earning types)
            $statsQuery = LedgerEntry::where('user_id', $partnerId)
                ->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT', 'PAS_COMMISSION']);

            // Apply same date filters to stats
            if ($request->filled('date_from')) {
                $statsQuery->where('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $statsQuery->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // NOTE: PAS_COMMISSION is stored as a positive amount (debit), so it
            // must be subtracted to get the net partner earnings. PAS_CASH_SETTLEMENT
            // is excluded: it only removes the cash-collected share from the
            // available balance and is not an earning.
            $stats = $statsQuery->selectRaw("
                SUM(CASE WHEN type = 'PAS_COMMISSION' THEN -amount ELSE amount END) as total_earnings,
                COUNT(*) as total_jobs,
                AVG(CASE WHEN type = 'PAS_COMMISSION' THEN -amount ELSE amount END) as avg_earning_per_job
            ")->first();

            // Today's earnings (includes all earning types)
            $todayEarnings = LedgerEntry::where('user_id', $partnerId)
                ->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT', 'PAS_COMMISSION'])
                ->whereDate('created_at', today())
                ->selectRaw("SUM(CASE WHEN type = 'PAS_COMMISSION' THEN -amount ELSE amount END) as total")
                ->value('total');

            // Live pending balance: money requested but not yet paid out
            $pendingPayouts = PartnerPayout::where('partner_id', $partnerId)
                ->whereIn('status', ['pending', 'processing'])
                ->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'earnings' => $earnings,
                    'balance' => [
                        'available_balance' => (float) ($partnerBalance?->available_balance ?? 0),
                        'pending_balance' => (float) $pendingPayouts,
                        'total_earned' => (float) ($partnerBalance?->total_earnings ?? 0),
                        'total_withdrawn' => (float) ($partnerBalance?->total_withdrawn ?? 0),
                    ],
                    'stats' => [
                        'today_earnings' => $todayEarnings,
                        'total_earnings' => $stats->total_earnings ?? 0,
                        'total_jobs' => $stats->total_jobs ?? 0,
                        'avg_earning_per_job' => $stats->avg_earning_per_job ?? 0
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading earnings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DEPRECATED: Legacy payout request method - replaced by PartnerPayoutController::requestPayout()
     * This method is disabled to prevent conflicts with the main payout system.
     * Use PartnerPayoutController::requestPayout() instead.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This payout endpoint is deprecated. Please use the main payout system at /api/v1/payouts/request',
            'deprecated' => true,
            'correct_endpoint' => '/api/v1/payouts/request'
        ], 410); // 410 Gone - indicates this endpoint is no longer available
    }

    /*
    // ORIGINAL LEGACY METHOD - DISABLED TO PREVENT CONFLICTS WITH PartnerPayoutController
    public function requestPayout_LEGACY(Request $request): JsonResponse
    {
        $partnerId = $request->user()->partnerProfile?->partner_id;

        if (!$partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'bank_details' => 'required|array',
            'bank_details.account_number' => 'required|string',
            'bank_details.bank_name' => 'required|string',
            'bank_details.account_holder_name' => 'required|string',
        ]);

        $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

        if (!$partnerBalance || $partnerBalance->available_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create payout request
            $payout = PartnerPayout::create([
                'partner_id' => $partnerId,
                'amount' => $request->amount,
                'bank_details' => $request->bank_details,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            // Update partner balance - move from available to pending
            $partnerBalance->decrement('available_balance', $request->amount);
            $partnerBalance->increment('pending_balance', $request->amount);

            // NOTE: Ledger entry creation moved to PartnerPayoutController
            // This method appears to be legacy - current system uses PartnerPayoutController.requestPayout()

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully',
                'data' => [
                    'payout_id' => $payout->id,
                    'amount' => $payout->amount,
                    'status' => $payout->status
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payout request'
            ], 500);
        }
    }
    */
}
