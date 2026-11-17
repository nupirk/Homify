<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayout;
use App\Models\PartnerBalance;
use App\Models\LedgerEntry;
use App\Support\Redactor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PartnerPayoutController extends Controller
{
    /**
     * Get partner's payout history
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $payouts = PartnerPayout::where('partner_id', $partnerProfile->partner_id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $payouts
        ]);
    }

    /**
     * Request a payout (withdrawal) - MAIN ACTIVE PAYOUT SYSTEM
     * This is the primary and only payout endpoint that should be used.
     * Frontend should use this endpoint: POST /api/v1/payouts/request
     */
    public function requestPayout(Request $request): JsonResponse
    {
        // Bank/wallet identifiers and the Authorization header are never
        // logged - only the shape needed to debug a payout.
        Log::info('Payout request received', [
            'user_id' => $request->user()?->user_id,
            'payment_method' => $request->input('payment_method'),
            'amount' => $request->input('amount'),
        ]);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100', // Minimum NPR 100
            'payment_method' => 'required|in:bank_transfer,esewa,khalti,connectips,cash',
            'bank_transfer_details' => 'required_if:payment_method,bank_transfer|array',
            'bank_transfer_details.account_number' => 'required_if:payment_method,bank_transfer|string',
            'bank_transfer_details.account_name' => 'required_if:payment_method,bank_transfer|string',
            'bank_transfer_details.bank_name' => 'required_if:payment_method,bank_transfer|string',
            'bank_transfer_details.branch' => 'required_if:payment_method,bank_transfer|string',
            'esewa_number' => 'required_if:payment_method,esewa|string',
            'khalti_number' => 'required_if:payment_method,khalti|string',
            'connectips_details' => 'required_if:payment_method,connectips|array',
            'connectips_details.account_number' => 'required_if:payment_method,connectips|string',
            'connectips_details.account_name' => 'required_if:payment_method,connectips|string',
            'connectips_details.bank_code' => 'required_if:payment_method,connectips|string',
            'connectips_details.bank_name' => 'required_if:payment_method,connectips|string',
            'cash_details' => 'nullable|array',
            'cash_details.collection_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            Log::warning('Payout request validation failed', [
                'user_id' => $request->user()?->user_id,
                'errors' => $validator->errors(),
                'request_data' => Redactor::redactKeys(
                    $request->all(),
                    Redactor::sensitiveKeys()
                )
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        // PREPAID MODEL: partner's cached balance; missing row means zero balance
        $partnerBalance = PartnerBalance::where('partner_id', $partnerProfile->partner_id)->first();

        if (!$partnerBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Partner balance not found',
                'details' => ['available_balance' => 0]
            ], 404);
        }

        $partnerId = $partnerProfile->partner_id;

        try {
            DB::beginTransaction();

            // Lock the balance row and re-read pending payouts inside the
            // transaction so concurrent requests cannot oversubscribe.
            $lockedBalance = PartnerBalance::where('partner_id', $partnerId)
                ->lockForUpdate()
                ->first();

            if (!$lockedBalance) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Partner balance not found'
                ], 404);
            }
            $partnerBalance = $lockedBalance;

            // Calculate total pending payout amount
            $pendingPayouts = PartnerPayout::where('partner_id', $partnerId)
                ->whereIn('status', ['pending', 'processing'])
                ->sum('amount');

            // Calculate actual available balance (considering pending payouts)
            $actualAvailableBalance = $partnerBalance->available_balance - $pendingPayouts;

            Log::info('Payout validation check', [
                'partner_id' => $partnerId,
                'stored_available_balance' => $partnerBalance->available_balance,
                'pending_payouts' => $pendingPayouts,
                'actual_available_balance' => $actualAvailableBalance,
                'requested_amount' => $request->amount
            ]);

            // Check if partner has enough balance after considering pending payouts
            if ($actualAvailableBalance < (float) $request->amount) {
                DB::rollBack();

                // MODEL A: a negative available balance means COD commissions are
                // owed to the platform (cash was kept by the partner). Explain it.
                if ($actualAvailableBalance <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your available balance is negative because platform commissions from COD jobs are owed. Your balance will become available as new online earnings are added.',
                        'details' => [
                            'available_balance' => $partnerBalance->available_balance,
                            'pending_payouts' => $pendingPayouts,
                            'actual_available' => $actualAvailableBalance,
                            'requested_amount' => $request->amount
                        ]
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient available balance for withdrawal. Consider pending payout requests.',
                    'details' => [
                        'stored_balance' => $partnerBalance->available_balance,
                        'pending_payouts' => $pendingPayouts,
                        'actual_available' => $actualAvailableBalance,
                        'requested_amount' => $request->amount
                    ]
                ], 400);
            }

            // Prevent multiple large payout requests
            $recentPendingPayouts = PartnerPayout::where('partner_id', $partnerId)
                ->whereIn('status', ['pending', 'processing'])
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($recentPendingPayouts >= 3) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Too many pending payout requests. Please wait for current requests to be processed or contact support.',
                    'pending_requests_count' => $recentPendingPayouts
                ], 429);
            }

            // Calculate platform fee (2% for processing)
            $platformFee = round((float) $request->amount * 0.02, 2);
            $netAmount = round((float) $request->amount - $platformFee, 2);

            // Prepare payout destination details
            $bankTransferDetails = null;
            if ($request->payment_method === 'bank_transfer') {
                $bankTransferDetails = $request->bank_transfer_details;
            } elseif ($request->payment_method === 'esewa') {
                $bankTransferDetails = ['esewa_number' => $request->esewa_number];
            } elseif ($request->payment_method === 'khalti') {
                $bankTransferDetails = ['khalti_number' => $request->khalti_number];
            } elseif ($request->payment_method === 'connectips') {
                $bankTransferDetails = $request->connectips_details;
            } elseif ($request->payment_method === 'cash') {
                $bankTransferDetails = ['collection_notes' => $request->input('cash_details.collection_notes', '')];
            }

            // Create payout record
            $payout = PartnerPayout::create([
                'partner_id' => $partnerId,
                'amount' => $request->amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'bank_transfer_details' => $bankTransferDetails,
                'initiated_at' => now(),
                'metadata' => [
                    'requested_by' => $user->user_id,
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip()
                ]
            ]);

            // PREPAID MODEL: Just create payout record, don't change balance yet
            // Balance is changed when admin processes/completes the payout.
            // No ledger entry created - WITHDRAWAL ledger entry is created on
            // admin approval / completion.

            DB::commit();

            Log::info('Partner payout requested successfully', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $partnerId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method
            ]);

            \App\Services\AuditService::record($user->user_id, 'payout_requested', 'payout', $payout->payout_id, null, [
                'amount' => $payout->amount,
                'net_amount' => $payout->net_amount,
                'payment_method' => $payout->payment_method,
            ]);

            \App\Services\NotificationService::sendWithPush(
                $partnerId,
                'payout_requested',
                'Payout Request Received',
                "Your payout request of NPR " . number_format((float) $payout->amount, 2) . " has been submitted.",
                ['payout_id' => $payout->payout_id, 'amount' => $payout->amount]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully',
                'data' => [
                    'payout_id' => $payout->payout_id,
                    'amount' => $payout->amount,
                    'platform_fee' => $payout->platform_fee,
                    'net_amount' => $payout->net_amount,
                    'status' => $payout->status,
                    'payment_method' => $payout->payment_method,
                    'initiated_at' => $payout->initiated_at
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create payout request', [
                'partner_id' => $partnerId,
                'amount' => $request->amount,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payout request. Please try again.'
            ], 500);
        }
    }

    /**
     * Get partner's current balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        $balance = PartnerBalance::where('partner_id', $partnerProfile->partner_id)->first();

        // Calculate total pending payout amount
        $pendingPayouts = PartnerPayout::where('partner_id', $partnerProfile->partner_id)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        // Calculate actual available balance (considering pending payouts).
        // NOTE: the raw value is returned even when negative - a partner whose
        // PAS commissions exceed their cash-collected share owes the platform,
        // and that debt must be visible (not silently clamped to 0).
        $actualAvailableBalance = ($balance ? $balance->available_balance : 0) - $pendingPayouts;

        // Get pending payout requests for display
        $pendingPayoutRequests = PartnerPayout::where('partner_id', $partnerProfile->partner_id)
            ->whereIn('status', ['pending', 'processing'])
            ->select('payout_id', 'amount', 'status', 'payment_method', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // A failed payout is the end of a request the partner made, so it has
        // to be visible with the reason the admin recorded - otherwise the
        // request just disappears and the partner cannot tell whether the
        // money is coming.
        $failedPayoutRequests = PartnerPayout::where('partner_id', $partnerProfile->partner_id)
            ->where('status', 'failed')
            ->select('payout_id', 'amount', 'status', 'payment_method', 'created_at', 'failure_reason')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stored_balance' => $balance ? $balance->available_balance : 0,  // Original stored balance
                'pending_payouts' => $pendingPayouts,           // Total pending amount
                'available_balance' => $actualAvailableBalance, // Actual balance (may be negative when the partner owes the platform)
                'total_earnings' => $balance ? $balance->total_earnings : 0,    // Fixed field name
                'total_withdrawn' => $balance ? $balance->total_withdrawn : 0,
                'last_updated' => $balance ? $balance->last_updated : null,
                'pending_requests' => $pendingPayoutRequests,   // List of pending requests
                'failed_requests' => $failedPayoutRequests,     // Recent failures, with the reason
            ]
        ]);
    }

    /**
     * Cancel a pending payout
     */
    public function cancelPayout(Request $request, $payoutId): JsonResponse
    {
        $user = $request->user();
        $partnerProfile = $user->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Lock the payout row and only cancel if it is still pending.
            // The status is re-verified in the UPDATE so a concurrent admin
            // processing cannot be overwritten by this cancellation.
            $payout = PartnerPayout::where('payout_id', $payoutId)
                ->where('partner_id', $partnerProfile->partner_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$payout) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payout not found or cannot be cancelled'
                ], 404);
            }

            // Update payout status (conditional on status still being pending)
            $updated = PartnerPayout::where('payout_id', $payout->payout_id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'metadata' => array_merge($payout->metadata ?? [], [
                        'cancelled_by' => $user->user_id,
                        'cancelled_at' => now()->toISOString()
                    ])
                ]);

            if ($updated === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payout not found or cannot be cancelled'
                ], 404);
            }

            // PREPAID MODEL: No balance changes needed since money wasn't reserved

            DB::commit();

            \App\Services\AuditService::record($user->user_id, 'payout_cancelled', 'payout', $payout->payout_id, [
                'status' => 'pending',
            ], [
                'status' => 'cancelled',
                'amount' => $payout->amount,
            ]);

            \App\Services\NotificationService::sendWithPush(
                $partnerProfile->partner_id,
                'payout_cancelled',
                'Payout Cancelled',
                "Your payout request of NPR " . number_format((float) $payout->amount, 2) . " has been cancelled.",
                ['payout_id' => $payout->payout_id, 'amount' => $payout->amount]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout cancelled successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to cancel payout', [
                'payout_id' => $payoutId,
                'partner_id' => $partnerProfile->partner_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payout. Please try again.'
            ], 500);
        }
    }
}
