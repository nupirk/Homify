<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SchemaCompat;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Order;
use App\Models\PartnerPayout;
use App\Models\PartnerBalance;
use App\Services\PartnerStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;
use Dompdf\Options;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $query = LedgerEntry::with(['user', 'order']);

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        // Search by narration
        if ($request->filled('search')) {
            $query->where('narration', SchemaCompat::likeOperator(), '%' . $request->search . '%');
        }

        // Order by latest first
        $ledgerEntries = $query->orderBy('created_at', 'asc')
            ->paginate(50)
            ->appends($request->query());

        // Calculate page totals
        $pageTotals = [
            'page_total' => $ledgerEntries->sum('amount'),
            'page_count' => $ledgerEntries->count(),
        ];

        // Get summary statistics
        $stats = $this->getLedgerStats($request);

        // Get filter options
        $users = User::select('user_id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->get();

        $entryTypes = LedgerEntry::distinct('type')
            ->pluck('type')
            ->sort();

        return view('admin.ledger.index', compact(
            'ledgerEntries',
            'stats',
            'users',
            'entryTypes',
            'pageTotals'
        ));
    }

    public function show($id)
    {
        $ledgerEntry = LedgerEntry::with(['user', 'order'])->findOrFail($id);

        // Get related entries for the same booking
        $relatedEntries = [];
        if ($ledgerEntry->booking_id) {
            $relatedEntries = LedgerEntry::with(['user'])
                ->where('booking_id', $ledgerEntry->booking_id)
                ->where('id', '!=', $id)
                ->orderBy('created_at')
                ->get();
        }

        return view('super-admin.ledger.show', compact('ledgerEntry', 'relatedEntries'));
    }

    public function userBalance(Request $request)
    {
        // Real-balance semantics: a user's balance is the balance_after of their
        // latest ledger entry (the chain is kept consistent by LedgerService),
        // not a raw SUM of signed amounts.
        $users = User::select('user_id', 'first_name', 'last_name', 'email', 'user_type')
            ->orderBy('first_name')
            ->paginate(20);

        $userIds = $users->pluck('user_id')->filter();

        // Latest balance_after per user
        $latestByUser = LedgerEntry::whereIn('user_id', $userIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['user_id', 'balance_after'])
            ->groupBy('user_id')
            ->map(fn($entries) => $entries->last()->balance_after);

        // Transaction counts per user
        $countsByUser = LedgerEntry::whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        foreach ($users as $user) {
            $user->current_balance = (float) ($latestByUser[$user->user_id] ?? 0);
            $user->transaction_count = (int) ($countsByUser[$user->user_id] ?? 0);
        }

        // Summary stats from real balances
        $allLatest = LedgerEntry::selectRaw('user_id, MAX(id) as max_id')
            ->groupBy('user_id')
            ->get();

        $latestRows = collect();
        if ($allLatest->isNotEmpty()) {
            $latestRows = LedgerEntry::whereIn('id', $allLatest->pluck('max_id'))
                ->get(['id', 'user_id', 'balance_after']);
        }

        $positiveBalances = $latestRows->filter(fn($e) => $e->balance_after > 0);
        $negativeBalances = $latestRows->filter(fn($e) => $e->balance_after < 0);

        $stats = [
            'total_users' => User::count(),
            'users_with_balance' => $latestRows->filter(fn($e) => (float) $e->balance_after != 0)->count(),
            'positive_balance_users' => $positiveBalances->count(),
            'negative_balance_users' => $negativeBalances->count(),
            'total_positive_balance' => $positiveBalances->sum('balance_after'),
            'total_negative_balance' => $negativeBalances->sum('balance_after'),
        ];

        // Calculate totals for current page
        $pageTotals = [
            'total_transactions' => $users->sum('transaction_count')
        ];

        return view('admin.ledger.balances', ['userBalances' => $users], compact('stats', 'pageTotals'));
    }

    public function orderLedger($bookingId)
    {
        $order = Order::with(['user', 'items.service'])->where('order_id', $bookingId)->first();

        $ledgerEntries = LedgerEntry::with(['user'])
            ->where('booking_id', $bookingId)
            ->orderBy('created_at')
            ->get();

        // Calculate comprehensive breakdown
        $breakdown = $this->calculateOrderBreakdown($ledgerEntries);

        // Get transaction flow
        $transactionFlow = $this->getTransactionFlow($ledgerEntries);

        // Calculate verification totals
        $totalDebits = $ledgerEntries->whereIn('type', ['CUSTOMER_DEBIT', 'WITHDRAWAL', 'REFUND'])->sum('amount');
        $totalCredits = $ledgerEntries->whereIn('type', ['ESCROW_CREDIT', 'PARTNER_EARNING', 'PLATFORM_FEE', 'TAX_PAYABLE'])->sum('amount');

        // Calculate page totals
        $pageTotals = [
            'total_amount' => $ledgerEntries->sum('amount'),
            'total_debits' => $ledgerEntries->where('amount', '<', 0)->sum('amount'),
            'total_credits' => $ledgerEntries->where('amount', '>', 0)->sum('amount'),
        ];

        // Enhanced stats
        $stats = [
            'total_entries' => $ledgerEntries->count(),
            'unique_users' => $ledgerEntries->unique('user_id')->count(),
            'total_amount' => $ledgerEntries->sum('amount'),
            'first_entry' => $ledgerEntries->min('created_at'),
            'last_entry' => $ledgerEntries->max('created_at'),
        ];

        return view('admin.ledger.order', compact(
            'order',
            'ledgerEntries',
            'totalDebits',
            'totalCredits',
            'breakdown',
            'transactionFlow',
            'stats',
            'bookingId',
            'pageTotals'
        ));
    }

    public function userLedger($userId)
    {
        $user = User::findOrFail($userId);

        $ledgerEntries = LedgerEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        $currentBalance = LedgerEntry::getUserBalance($userId);

        // Get monthly summary
        $monthlyStats = LedgerEntry::where('user_id', $userId)
            ->selectRaw('
                DATE_TRUNC(\'month\', created_at) as month,
                type,
                SUM(amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('month', 'type')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return view('super-admin.ledger.user', compact('user', 'ledgerEntries', 'currentBalance', 'monthlyStats'));
    }

    public function export(Request $request)
    {
        $query = LedgerEntry::with(['user', 'order']);

        // Apply same filters as index
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $entries = $query->orderBy('created_at', 'desc')->get();

        $filename = 'ledger_export_' . now()->format('Y_m_d_H_i_s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($entries) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Booking ID',
                'User ID',
                'User Name',
                'User Email',
                'Type',
                'Amount',
                'Currency',
                'Balance After',
                'Narration',
                'Created At'
            ]);

            // CSV data
            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->id,
                    $entry->booking_id,
                    $entry->user_id,
                    $entry->user ? $entry->user->first_name . ' ' . $entry->user->last_name : '',
                    $entry->user ? $entry->user->email : '',
                    $entry->type,
                    $entry->amount,
                    $entry->currency,
                    $entry->balance_after,
                    $entry->narration,
                    $entry->created_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getLedgerStats($request)
    {
        $query = LedgerEntry::query();

        // Apply same filters as main query
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return [
            'total_entries' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_type' => $query->selectRaw('type, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('type')
                ->orderByRaw('SUM(amount) DESC')
                ->get(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'unique_bookings' => $query->whereNotNull('booking_id')->distinct('booking_id')->count('booking_id'),
        ];
    }

    /**
     * Calculate detailed order breakdown with earnings analysis
     */
    private function calculateOrderBreakdown($ledgerEntries)
    {
        $customerDebit = $ledgerEntries->firstWhere('type', 'CUSTOMER_DEBIT');
        $taxPayable = $ledgerEntries->firstWhere('type', 'TAX_PAYABLE');
        $partnerEarning = $ledgerEntries->firstWhere('type', 'PARTNER_EARNING');
        $platformFee = $ledgerEntries->firstWhere('type', 'PLATFORM_FEE');
        $escrowCredit = $ledgerEntries->firstWhere('type', 'ESCROW_CREDIT');

        $breakdown = [
            'customer_paid' => $customerDebit ? $customerDebit->amount : 0,
            'tax_amount' => $taxPayable ? $taxPayable->amount : 0,
            'service_amount' => 0,
            'partner_earning' => $partnerEarning ? $partnerEarning->amount : 0,
            'platform_fee' => $platformFee ? $platformFee->amount : 0,
            'escrow_amount' => $escrowCredit ? $escrowCredit->amount : 0,
        ];

        // Calculate untaxed service amount
        if ($customerDebit && $taxPayable) {
            $breakdown['service_amount'] = $customerDebit->amount - $taxPayable->amount;
        } elseif ($customerDebit) {
            $breakdown['service_amount'] = $customerDebit->amount;
        }

        // Calculate commission rate if possible
        if ($breakdown['service_amount'] > 0 && $breakdown['partner_earning'] > 0) {
            $breakdown['commission_rate'] = round(($breakdown['partner_earning'] / $breakdown['service_amount']) * 100, 2);
        } else {
            $breakdown['commission_rate'] = 0;
        }

        // Verify calculations
        $breakdown['calculated_platform_fee'] = $breakdown['service_amount'] - $breakdown['partner_earning'];
        $breakdown['fee_variance'] = $breakdown['platform_fee'] - $breakdown['calculated_platform_fee'];

        return $breakdown;
    }

    /**
     * Get transaction flow with detailed timeline
     */
    private function getTransactionFlow($ledgerEntries)
    {
        $flow = [];
        $runningBalance = 0;

        foreach ($ledgerEntries->sortBy('created_at') as $entry) {
            $runningBalance += $entry->amount;

            $flow[] = [
                'id' => $entry->id,
                'timestamp' => $entry->created_at,
                'type' => $entry->type,
                'user_id' => $entry->user_id,
                'user_name' => $entry->user ? "{$entry->user->first_name} {$entry->user->last_name}" : "User #{$entry->user_id}",
                'amount' => $entry->amount,
                'running_balance' => $runningBalance,
                'narration' => $entry->narration,
                'is_debit' => $entry->amount < 0,
                'is_credit' => $entry->amount > 0,
            ];
        }

        return $flow;
    }

    /**
     * Get comprehensive analytics dashboard
     */
    public function analytics(Request $request)
    {
        // Date range filter
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Daily transaction volumes
        $dailyStats = LedgerEntry::selectRaw('
                DATE(created_at) as date,
                type,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        // Revenue breakdown
        $revenueBreakdown = LedgerEntry::selectRaw('
                type,
                COUNT(*) as count,
                SUM(amount) as total,
                AVG(amount) as average
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('type')
            ->orderByRaw('SUM(amount) DESC')
            ->get();

        // Top users by transaction volume
        $topUsers = LedgerEntry::with('user')
            ->selectRaw('
                user_id,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('user_id')
            ->orderBy('total_amount', 'desc')
            ->limit(10)
            ->get();

        // Platform metrics
        $platformMetrics = [
            'total_revenue' => LedgerEntry::where('type', 'PLATFORM_FEE')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('amount'),
            'total_partner_earnings' => LedgerEntry::where('type', 'PARTNER_EARNING')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('amount'),
            'total_tax_collected' => LedgerEntry::where('type', 'TAX_PAYABLE')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('amount'),
            'total_customer_payments' => LedgerEntry::where('type', 'CUSTOMER_DEBIT')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('amount'),
        ];

        // Calculate averages and ratios
        $totalOrders = LedgerEntry::where('type', 'CUSTOMER_DEBIT')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->distinct('booking_id')
            ->count();

        $platformMetrics['average_order_value'] = $totalOrders > 0 ?
            $platformMetrics['total_customer_payments'] / $totalOrders : 0;

        $platformMetrics['platform_commission_rate'] = $platformMetrics['total_customer_payments'] > 0 ?
            ($platformMetrics['total_revenue'] / $platformMetrics['total_customer_payments']) * 100 : 0;

        return view('admin.ledger.analytics', compact(
            'dailyStats',
            'revenueBreakdown',
            'topUsers',
            'platformMetrics',
            'dateFrom',
            'dateTo',
            'totalOrders'
        ));
    }

    /**
     * Generate reconciliation report
     */
    public function reconciliation(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Get all bookings with their ledger entries
        $bookingsWithEntries = LedgerEntry::selectRaw('
                booking_id,
                COUNT(*) as entry_count,
                SUM(CASE WHEN type = \'CUSTOMER_DEBIT\' THEN amount ELSE 0 END) as customer_paid,
                SUM(CASE WHEN type = \'TAX_PAYABLE\' THEN amount ELSE 0 END) as tax_collected,
                SUM(CASE WHEN type = \'PARTNER_EARNING\' THEN amount ELSE 0 END) as partner_earned,
                SUM(CASE WHEN type = \'PLATFORM_FEE\' THEN amount ELSE 0 END) as platform_fee,
                SUM(amount) as total_flow
            ')
            ->whereNotNull('booking_id')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 0')
            ->get();

        // Find discrepancies
        $discrepancies = [];
        foreach ($bookingsWithEntries as $booking) {
            $serviceAmount = $booking->customer_paid - $booking->tax_collected;
            $expectedTotal = $booking->partner_earned + $booking->platform_fee;
            $variance = abs($serviceAmount - $expectedTotal);

            if ($variance > 0.01) { // More than 1 paisa difference
                $discrepancies[] = [
                    'booking_id' => $booking->booking_id,
                    'customer_paid' => $booking->customer_paid,
                    'service_amount' => $serviceAmount,
                    'partner_earned' => $booking->partner_earned,
                    'platform_fee' => $booking->platform_fee,
                    'expected_total' => $expectedTotal,
                    'variance' => $variance,
                ];
            }
        }

        // Summary stats
        $summary = [
            'total_bookings' => $bookingsWithEntries->count(),
            'total_customer_payments' => $bookingsWithEntries->sum('customer_paid'),
            'total_tax_collected' => $bookingsWithEntries->sum('tax_collected'),
            'total_partner_earnings' => $bookingsWithEntries->sum('partner_earned'),
            'total_platform_fees' => $bookingsWithEntries->sum('platform_fee'),
            'discrepancies_count' => count($discrepancies),
            'total_variance' => collect($discrepancies)->sum('variance'),
        ];

        return view('admin.ledger.reconciliation', compact(
            'bookingsWithEntries',
            'discrepancies',
            'summary',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Partner payout management
     */
    public function partnerPayouts(Request $request)
    {
        $query = PartnerPayout::query()
            ->with(['partner:user_id,first_name,last_name,email'])
            ->latest();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payouts = $query->paginate(20);

        // Calculate total earnings for current filters
        $totalEarnings = PartnerPayout::query()
            ->when($request->filled('status'), function ($q) use ($request) {
                return $q->where('status', $request->status);
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                return $q->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                return $q->whereDate('created_at', '<=', $request->date_to);
            })
            ->sum('amount');

        // Get pending partners with earnings
        $pendingPartners = User::query()
            ->whereHas('partnerPayouts', function ($q) {
                $q->where('status', 'pending');
            })
            ->with(['partnerPayouts' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->get();

        // Calculate payout summary
        $payoutSummary = [
            'total_earnings' => PartnerPayout::sum('amount'),
            'total_payouts' => PartnerPayout::where('status', 'completed')->sum('amount'),
            'pending_payouts' => PartnerPayout::where('status', 'pending')->sum('amount'),
        ];

        // Get partner earnings (for legacy section of view)
        $partnerEarnings = User::query()
            ->select(['user_id', 'first_name', 'last_name', 'email', 'phone'])
            ->where('user_type', 'partner')
            ->withCount(['partnerPayouts as total_payouts'])
            ->withSum(['partnerPayouts as total_earnings'], 'amount')
            ->withSum(['partnerPayouts as pending_payout' => function ($query) {
                $query->where('status', 'pending');
            }], 'amount')
            ->withMax(['partnerPayouts as last_earning_date'], 'created_at')
            ->get()
            ->map(function ($partner) {
                // Ensure default values for calculations
                $partner->total_earnings = $partner->total_earnings ?? 0;
                $partner->pending_payout = $partner->pending_payout ?? 0;
                $partner->total_payouts = abs($partner->total_payouts ?? 0);
                $partner->completed_orders = 0; // Set to 0 for now since bookings table doesn't exist
                return $partner;
            });

        // Convert to paginated result manually since we used get() and map()
        $currentPage = request()->get('earnings_page', 1);
        $perPage = 20;
        $partnerEarnings = new \Illuminate\Pagination\LengthAwarePaginator(
            $partnerEarnings->forPage($currentPage, $perPage),
            $partnerEarnings->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'earnings_page']
        );

        // Get recent payouts
        $recentPayouts = PartnerPayout::with(['partner:user_id,first_name,last_name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.ledger.partner-payouts', compact('payouts', 'totalEarnings', 'pendingPartners', 'payoutSummary', 'partnerEarnings', 'recentPayouts'));
    }

    /**
     * Process partner payout
     */
    public function processPartnerPayout(Request $request)
    {
        $request->validate([
            'payout_id' => 'required|exists:partner_payouts,payout_id',
            'payment_method' => 'required|in:bank_transfer,esewa,khalti,cash',
            'transaction_reference' => 'required_unless:payment_method,cash|nullable|string|max:255',
            'gateway_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ], [
            'transaction_reference.required_unless' => 'Enter the transaction ID / reference you used to send the money. It is required for every method except cash.',
        ]);

        $payout = PartnerPayout::findOrFail($request->payout_id);

        if ($payout->status !== 'pending') {
            if ($request->ajax()) {
                return response()->json(['error' => 'Only pending payouts can be processed.'], 400);
            }
            return back()->with('error', 'Only pending payouts can be processed.');
        }

        // Every method is a manual transfer. eSewa's ePay API only *collects*
        // money into the platform's own merchant wallet - it has no endpoint
        // that disburses funds to a partner's wallet - so redirecting an admin
        // to an eSewa checkout never paid the partner. The admin sends the
        // money from the platform eSewa account and records the eSewa
        // transaction ID here, then marks the payout completed.
        return $this->processManualPayout($payout, $request);
    }

    /**
     * Record a payout the admin has already sent to the partner manually.
     *
     * Covers every payment method (bank transfer, eSewa, Khalti, cash): the
     * money moves outside the platform and the admin stores the reference.
     */
    private function processManualPayout(PartnerPayout $payout, Request $request)
    {
        try {
            DB::beginTransaction();

            // Lock the payout row and re-verify it is still pending so two
            // admins (or the partner's cancellation) cannot race past this gate.
            $lockedPayout = PartnerPayout::where('payout_id', $payout->payout_id)
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout || $lockedPayout->status !== 'pending') {
                DB::rollBack();
                return back()->with('error', 'Only pending payouts can be processed.');
            }
            $payout = $lockedPayout;

            // BEFORE processing payout, deduct COD commissions from partner's available balance
            $ledgerService = app(\App\Services\LedgerService::class);
            $codCommissionDeducted = $ledgerService->deductPendingPASCommissions($payout->partner_id);

            Log::info('COD commission deduction result', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $payout->partner_id,
                'commission_deducted' => $codCommissionDeducted
            ]);

            // After COD commission deduction the partner may no longer have enough
            // available balance to cover the payout - reject instead of going negative
            $partnerLastBalance = LedgerEntry::where('user_id', $payout->partner_id)
                ->orderByDesc('created_at')
                ->value('balance_after') ?? 0;

            if ($payout->amount > $partnerLastBalance + 0.01) {
                throw new \Exception(
                    "Insufficient available balance after COD commission deduction. " .
                    "Available: " . number_format($partnerLastBalance, 2) . ", Payout: " . number_format($payout->amount, 2)
                );
            }

            // Deduct the net payout and the gateway fee from the ledger in one entry,
            // then update the payout status (still conditional on pending).
            //
            // Reflect the chosen method/reference on the model BEFORE the ledger
            // entry is written so the narration carries the real transfer
            // reference instead of whatever the payout had before.
            $payout->payment_method = $request->payment_method;
            $payout->transaction_reference = $request->transaction_reference;
            $payout->gateway_fee = $request->gateway_fee ?? 0;

            $ledgerService->recordPayoutWithdrawal($payout, $request->payment_method);

            $updated = PartnerPayout::where('payout_id', $payout->payout_id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'payment_method' => $request->payment_method,
                    'transaction_reference' => $request->transaction_reference,
                    'gateway_fee' => $request->gateway_fee ?? 0,
                    'processed_at' => now(),
                    'processed_by' => Auth::id(),
                    'notes' => $request->notes,
                    'metadata' => array_merge($payout->metadata ?? [], [
                        'processing_started_at' => now()->toISOString(),
                        'gateway_details' => [
                            'method' => $request->payment_method,
                            'reference' => $request->transaction_reference,
                            'fee' => $request->gateway_fee ?? 0
                        ],
                        'cod_commission_deducted' => $codCommissionDeducted
                    ])
                ]);

            if ($updated === 0) {
                throw new \Exception('Only pending payouts can be processed.');
            }

            DB::commit();

            \App\Services\AuditService::record(Auth::id(), 'payout_processing', 'payout', $payout->payout_id, [
                'status' => 'pending',
            ], [
                'status' => 'processing',
                'payment_method' => $request->payment_method,
                'amount' => $payout->amount,
            ]);

            return back()->with('success', 'Payout has been marked as processing.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process payout from here: ' . $e->getMessage());
        }
    }

    /**
     * Complete partner payout (called by webhook or manual confirmation)
     */
    public function completePartnerPayout(Request $request)
    {
        $request->validate([
            'payout_id' => 'required|exists:partner_payouts,payout_id',
            'gateway_payout_id' => 'nullable|string',
            'final_gateway_fee' => 'nullable|numeric|min:0',
            'completion_notes' => 'nullable|string|max:500'
        ]);

        $payout = PartnerPayout::findOrFail($request->payout_id);

        try {
            // Shared, race-safe finalization (ledger entry + status transition)
            // happens inside its own transaction and re-verifies the status.
            app(\App\Services\LedgerService::class)->finalizePayout(
                $payout->payout_id,
                [
                    'gateway_payout_id' => $request->gateway_payout_id,
                    'final_gateway_fee' => $request->final_gateway_fee ?? $payout->gateway_fee,
                    'completed_by' => Auth::id(),
                ]
            );

            // Append admin completion notes once (repeated calls can no longer
            // duplicate them since the status is only 'processing' the first time).
            if ($request->filled('completion_notes')) {
                PartnerPayout::where('payout_id', $payout->payout_id)
                    ->where('status', 'completed')
                    ->update([
                        'notes' => ($payout->notes ?? '') . "\n" . $request->completion_notes
                    ]);
            }

            return back()->with('success', 'Payout has been marked as completed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to complete payout: ' . $e->getMessage());
        }
    }

    /**
     * Fail partner payout (when gateway rejects)
     */
    public function failPartnerPayout(Request $request)
    {
        $request->validate([
            'payout_id' => 'required|exists:partner_payouts,payout_id',
            'failure_reason' => 'required|string|max:500'
        ]);

        $payout = PartnerPayout::findOrFail($request->payout_id);

        try {
            // Shared, race-safe failure path: only payouts whose money was
            // actually withdrawn (manual processing) get their balance restored,
            // and the restoration happens once via the ledger reversal.
            $changed = app(\App\Services\LedgerService::class)->failPayout(
                $payout->payout_id,
                $request->failure_reason,
                'admin',
                Auth::id()
            );

            if (!$changed) {
                return back()->with(
                    'error',
                    'Payout #' . $payout->payout_id . ' was already ' . $payout->fresh()->status . ' - nothing was changed.'
                );
            }

            return back()->with('success', 'Payout has been marked as failed and balance restored.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process payout failure: ' . $e->getMessage());
        }
    }

    /**
     * Show partner payout details
     */
    public function partnerPayoutDetails($id)
    {
        $payout = PartnerPayout::with(['partner:user_id,first_name,last_name,email,phone'])
            ->findOrFail($id);

        // Get related ledger entries
        $relatedEntries = LedgerEntry::where('user_id', $payout->partner_id)
            ->where('created_at', '>=', $payout->created_at->subDays(7))
            ->where('created_at', '<=', $payout->created_at->addDays(7))
            ->latest()
            ->get();

        return view('admin.ledger.partner-payout-details', compact('payout', 'relatedEntries'));
    }

    /**
     * Reconcile partner balances with ledger entries
     */
    public function reconcilePartnerBalances(Request $request)
    {
        $partnerId = $request->get('partner_id');

        try {
            DB::beginTransaction();

            if ($partnerId) {
                // Reconcile specific partner
                $this->reconcileSinglePartner($partnerId);
                $message = "Partner balance reconciled successfully.";
            } else {
                // Reconcile all partners
                $partners = User::where('user_type', 'partner')->pluck('user_id');
                $reconciledCount = 0;

                foreach ($partners as $pid) {
                    if ($this->reconcileSinglePartner($pid)) {
                        $reconciledCount++;
                    }
                }

                $message = "Reconciled {$reconciledCount} partner balances.";
            }

            DB::commit();
            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Reconciliation failed: ' . $e->getMessage());
        }
    }

    /**
     * Reconcile a single partner's balance
     */
    private function reconcileSinglePartner($partnerId)
    {
        // Calculate actual balance from ledger entries
        // Include COD receipts (full amount credited) minus COD commissions (deducted at payout)
        $actualEarnings = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->sum('amount')
            + LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'PAS_RECEIPT')
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'PAS_COMMISSION')
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'EARNING_REVERSAL')
                ->sum('amount');

        $actualWithdrawals = abs(LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'WITHDRAWAL')
            ->sum('amount')) // This will be negative
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'WITHDRAWAL_REVERSAL')
                ->sum('amount'); // Reversals restore balance, so net them out

        // Calculate pending withdrawals
        $pendingWithdrawals = PartnerPayout::where('partner_id', $partnerId)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        // MODEL A: COD cash settlements (negative amounts) remove the
        // cash-collected share from the available balance
        $codCashSettlements = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->sum('amount');

        $actualAvailable = $actualEarnings - $actualWithdrawals - $pendingWithdrawals + $codCashSettlements;

        // Get or create partner balance record
        $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

        if (!$partnerBalance) {
            PartnerBalance::create([
                'partner_id' => $partnerId,
                'available_balance' => $actualAvailable,
                'total_earnings' => $actualEarnings,    // Fixed field name
                'total_withdrawn' => $actualWithdrawals,
                'last_updated' => now()
            ]);
            return true;
        }

        // Check if reconciliation is needed (PREPAID MODEL)
        $needsUpdate = (
            abs($partnerBalance->available_balance - $actualAvailable) > 0.01 ||
            abs($partnerBalance->total_earnings - $actualEarnings) > 0.01 ||    // Fixed field name
            abs($partnerBalance->total_withdrawn - $actualWithdrawals) > 0.01
        );

        if ($needsUpdate) {
            $partnerBalance->update([
                'available_balance' => $actualAvailable,
                'total_earnings' => $actualEarnings,    // Fixed field name
                'total_withdrawn' => $actualWithdrawals,
                'last_updated' => now()
            ]);

            // Log reconciliation (PREPAID MODEL)
            LedgerEntry::create([
                'user_id' => $partnerId,
                'booking_id' => null,
                'amount' => 0,
                'balance_after' => $actualAvailable,
                'type' => 'BALANCE_RECONCILIATION',
                'narration' => "Balance reconciled - Available: {$actualAvailable}, Total Earnings: {$actualEarnings}",
                'metadata' => [
                    'old_available' => $partnerBalance->available_balance,
                    'new_available' => $actualAvailable,
                    'old_earnings' => $partnerBalance->total_earnings,    // Fixed field name
                    'new_earnings' => $actualEarnings,
                    'reconciled_by' => Auth::id()
                ]
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get partner balance consistency report
     */
    public function partnerBalanceReport(Request $request)
    {
        // Get all partners with their calculated vs cached balances
        $partners = User::where('user_type', 'partner')->get();
        $inconsistencies = [];

        foreach ($partners as $partner) {
            // Calculate actual values from ledger
            // Include COD receipts (full amount credited) minus COD commissions (deducted at payout)
            $actualEarnings = LedgerEntry::where('user_id', $partner->user_id)
                ->where('type', 'PARTNER_EARNING')
                ->sum('amount')
                + LedgerEntry::where('user_id', $partner->user_id)
                    ->where('type', 'PAS_RECEIPT')
                    ->sum('amount')
                - LedgerEntry::where('user_id', $partner->user_id)
                    ->where('type', 'PAS_COMMISSION')
                    ->sum('amount')
                - LedgerEntry::where('user_id', $partner->user_id)
                    ->where('type', 'EARNING_REVERSAL')
                    ->sum('amount');

            $actualWithdrawals = abs(LedgerEntry::where('user_id', $partner->user_id)
                ->where('type', 'WITHDRAWAL')
                ->sum('amount'))
                - LedgerEntry::where('user_id', $partner->user_id)
                    ->where('type', 'WITHDRAWAL_REVERSAL')
                    ->sum('amount'); // Reversals restore balance, so net them out

            $pendingPayouts = PartnerPayout::where('partner_id', $partner->user_id)
                ->whereIn('status', ['pending', 'processing'])
                ->sum('amount');

            // MODEL A: COD cash settlements (negative amounts) remove the
            // cash-collected share from the available balance
            $codCashSettlements = LedgerEntry::where('user_id', $partner->user_id)
                ->where('type', 'PAS_CASH_SETTLEMENT')
                ->sum('amount');

            $actualAvailable = $actualEarnings - $actualWithdrawals - $pendingPayouts + $codCashSettlements;

            // Get cached values
            $cachedBalance = PartnerBalance::where('partner_id', $partner->user_id)->first();

            // Check for inconsistencies
            if (
                !$cachedBalance ||
                abs($cachedBalance->available_balance - $actualAvailable) > 0.01 ||
                abs($cachedBalance->total_earnings - $actualEarnings) > 0.01 ||
                abs($cachedBalance->total_withdrawn - $actualWithdrawals) > 0.01
            ) {

                $inconsistencies[] = [
                    'partner' => $partner,
                    'actual_earnings' => $actualEarnings,
                    'actual_withdrawals' => $actualWithdrawals,
                    'actual_available' => $actualAvailable,
                    'actual_pending' => $pendingPayouts,
                    'cached_earnings' => $cachedBalance ? $cachedBalance->total_earnings : 0,
                    'cached_withdrawals' => $cachedBalance ? $cachedBalance->total_withdrawn : 0,
                    'cached_available' => $cachedBalance ? $cachedBalance->available_balance : 0,
                    'cached_pending' => $pendingPayouts,
                ];
            }
        }

        return view('admin.ledger.partner-balance-report', compact('inconsistencies'));
    }

    /**
     * Decode the payload eSewa sends back on redirect.
     *
     * eSewa returns ?data=<base64 JSON> on the v2 checkout; older/probe
     * callbacks use plain query fields. Accept both shapes.
     */
    private function decodeEsewaCallback(Request $request): array
    {
        if ($request->filled('data')) {
            $decoded = json_decode((string) base64_decode((string) $request->input('data'), true), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->except('data');
    }

    /**
     * Verify the HMAC signature eSewa attaches to its redirect payload.
     *
     * Returns true/false when a signature is present, and null when the
     * payload carries none (so callers can refuse to trust it).
     */
    private function esewaCallbackSignatureValid(array $data): ?bool
    {
        if (empty($data['signature']) || empty($data['signed_field_names'])) {
            return null;
        }

        $fields = array_values(array_filter(explode(',', (string) $data['signed_field_names'])));

        if (!in_array('signed_field_names', $fields, true)) {
            $fields[] = 'signed_field_names';
        }

        $message = implode(',', array_map(
            fn ($field) => $field . '=' . ($data[$field] ?? ''),
            $fields
        ));

        $expected = base64_encode(hash_hmac(
            'sha256',
            $message,
            (string) config('payment.gateways.esewa.secret_key', ''),
            true
        ));

        return hash_equals($expected, (string) $data['signature']);
    }

    /**
     * Find the payout an eSewa callback refers to.
     */
    private function findEsewaPayout(array $data): ?PartnerPayout
    {
        $reference = $data['transaction_uuid'] ?? null;

        if (!$reference) {
            return null;
        }

        return PartnerPayout::where('transaction_reference', $reference)
            ->orWhere('metadata->esewa_transaction_uuid', $reference)
            ->first();
    }

    /**
     * Handle the eSewa checkout return URL.
     *
     * IMPORTANT: ePay only collects money into the platform's own merchant
     * wallet. It can never move funds to a partner's wallet, so a COMPLETE
     * response is NOT proof that the partner was paid. This handler therefore
     * never finalizes a payout - it records the callback and leaves the
     * payout in `processing` for an admin to confirm against the actual
     * eSewa transfer.
     */
    public function handleEsewaPayoutCallback(Request $request)
    {
        try {
            $data = $this->decodeEsewaCallback($request);
            Log::info('eSewa payout callback received', $data);

            if ($this->esewaCallbackSignatureValid($data) !== true) {
                Log::warning('eSewa payout callback rejected - missing or invalid signature', [
                    'transaction_uuid' => $data['transaction_uuid'] ?? null,
                ]);

                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('error', 'eSewa payout callback could not be verified (missing or invalid signature). No payout was changed.');
            }

            $payout = $this->findEsewaPayout($data);

            if (!$payout) {
                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('error', 'No payout matches the eSewa callback (reference: ' . ($data['transaction_uuid'] ?? 'unknown') . ').');
            }

            if ($payout->status === 'completed') {
                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('success', 'Payout #' . $payout->payout_id . ' is already completed.');
            }

            if (($data['status'] ?? null) === 'COMPLETE') {
                $payout->update([
                    'metadata' => array_merge($payout->metadata ?? [], [
                        'esewa_callback_status' => 'COMPLETE',
                        'esewa_callback_at' => now()->toISOString(),
                        'esewa_callback_reference' => $data['transaction_code'] ?? null,
                    ]),
                ]);

                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('error', 'eSewa reported COMPLETE for payout #' . $payout->payout_id
                        . ', but ePay cannot pay a partner. Confirm the transfer actually reached the partner, then mark the payout Completed with the eSewa transaction ID.');
            }

            return redirect()->route('admin.ledger.partner-payouts')
                ->with('error', 'eSewa payout #' . $payout->payout_id . ' is not complete (status: '
                    . ($data['status'] ?? 'unknown') . '). Nothing was changed.');
        } catch (\Exception $e) {
            Log::error('eSewa payout callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return redirect()->route('admin.ledger.partner-payouts')
                ->with('error', 'eSewa payout processing failed. Please contact support.');
        }
    }

    /**
     * Handle the eSewa checkout failure/cancel return URL.
     */
    public function handleEsewaPayoutFailure(Request $request)
    {
        try {
            $data = $this->decodeEsewaCallback($request);
            Log::info('eSewa payout failure received', $data);

            if ($this->esewaCallbackSignatureValid($data) !== true) {
                Log::warning('eSewa payout failure callback rejected - missing or invalid signature', [
                    'transaction_uuid' => $data['transaction_uuid'] ?? null,
                ]);

                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('error', 'eSewa payout callback could not be verified (missing or invalid signature). No payout was changed.');
            }

            $payout = $this->findEsewaPayout($data);

            if (!$payout) {
                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('error', 'No payout matches the eSewa failure callback.');
            }

            $status = strtoupper((string) ($data['status'] ?? 'CANCELED'));

            if (in_array($status, ['CANCELED', 'CANCELLED', 'NOT_FOUND', 'FULL_REFUND'], true)) {
                // No money left the platform wallet, so failing the payout only
                // restores the partner's reserved balance (when one was taken).
                app(\App\Services\LedgerService::class)->failPayout(
                    $payout->payout_id,
                    'eSewa checkout was not completed (status: ' . $status . ') - no money left the platform wallet',
                    'esewa_callback'
                );

                return redirect()->route('admin.ledger.partner-payouts')
                    ->with('success', 'Payout #' . $payout->payout_id . ' marked failed. Any reserved balance was restored.');
            }

            return redirect()->route('admin.ledger.partner-payouts')
                ->with('error', 'eSewa payout #' . $payout->payout_id . ' is still ' . $status . '. Nothing was changed.');
        } catch (\Exception $e) {
            Log::error('eSewa payout failure handler error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return redirect()->route('admin.ledger.partner-payouts')
                ->with('error', 'eSewa payout was cancelled or failed. Please try again.');
        }
    }

    /**
     * Generate and download partner statement PDF
     */
    public function generatePartnerStatement(Request $request, $partnerId)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'format' => 'nullable|in:detailed,summary'
        ]);

        try {
            $statementService = new PartnerStatementService();

            // Generate statement data
            $statementData = $statementService->generateStatementData(
                $partnerId,
                $request->date_from,
                $request->date_to
            );

            // Configure PDF options
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);

            // Create PDF instance
            $dompdf = new Dompdf($options);

            // Render HTML template
            $html = view('admin.statements.partner-statement', ['data' => $statementData])->render();

            // Load HTML content
            $dompdf->loadHtml($html);

            // Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait');

            // Render PDF
            $dompdf->render();

            // Generate filename
            $filename = $statementService->generateStatementFilename(
                $statementData['partner'],
                $statementData['period']['from'],
                $statementData['period']['to']
            );

            // Log statement generation
            Log::info('Partner statement generated', [
                'partner_id' => $partnerId,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'generated_by' => Auth::id(),
                'filename' => $filename
            ]);

            // Return PDF download response
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($dompdf->output()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate partner statement', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to generate statement: ' . $e->getMessage());
        }
    }

    /**
     * Show statement generation form
     */
    public function showStatementForm($partnerId)
    {
        $partner = User::with('partnerProfile')->findOrFail($partnerId);
        $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

        // Get date range suggestions
        $firstTransaction = LedgerEntry::where('user_id', $partnerId)
            ->orderBy('created_at', 'asc')
            ->first();

        $lastTransaction = LedgerEntry::where('user_id', $partnerId)
            ->orderBy('created_at', 'desc')
            ->first();

        return view('admin.statements.form', compact(
            'partner',
            'partnerBalance',
            'firstTransaction',
            'lastTransaction'
        ));
    }

    /**
     * Generate summary statement (lightweight version)
     */
    public function generateSummaryStatement(Request $request, $partnerId)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        try {
            $statementService = new PartnerStatementService();

            // Generate statement data
            $statementData = $statementService->generateStatementData(
                $partnerId,
                $request->date_from,
                $request->date_to
            );

            // Return JSON response for AJAX requests
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $statementData
                ]);
            }

            // Return view for regular requests
            return view('admin.statements.summary', ['data' => $statementData]);
        } catch (\Exception $e) {
            Log::error('Failed to generate summary statement', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate summary: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to generate summary: ' . $e->getMessage());
        }
    }
}
