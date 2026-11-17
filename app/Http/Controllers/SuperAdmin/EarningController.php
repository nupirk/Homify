<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SchemaCompat;
use Illuminate\Http\Request;
use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use App\Models\CommissionRule;
use App\Models\Service;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class EarningController extends Controller
{
    /**
     * Display a listing of earnings from ledger.
     */
    public function index(Request $request): View
    {
        $query = LedgerEntry::with(['user', 'order'])
            ->where('type', 'PARTNER_EARNING');

        // Filter by partner (user_id in ledger_entries)
        if ($request->filled('partner_id')) {
            $query->where('user_id', $request->partner_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by amount range
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }

        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        // Search by partner name
        if ($request->filled('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', SchemaCompat::likeOperator(), '%' . $request->search . '%');
            });
        }

        $earnings = $query->latest()->paginate(15);

        // Calculate summary statistics
        $totalEarnings = $query->sum('amount');
        $totalTransactions = $query->count();

        // Platform revenue (approximate from PLATFORM_FEE entries)
        $platformRevenue = LedgerEntry::where('type', 'PLATFORM_FEE')->sum('amount');

        // Get partners for filter dropdown
        $partners = User::whereHas('partnerProfile')->get();

        // Get services for filter dropdown
        $services = Service::all();

        // Set view type - defaults to 'all' if not specified
        $viewType = $request->get('view_type', 'all');

        return view('admin.earnings.index', compact(
            'earnings',
            'partners',
            'services',
            'totalEarnings',
            'totalTransactions',
            'platformRevenue',
            'viewType'
        ));
    }

    /**
     * Show earning statistics dashboard.
     */
    public function dashboard(Request $request): View
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        // Recent earnings data
        $recentEarnings = LedgerEntry::where('type', 'PARTNER_EARNING')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE(created_at) as date,
                SUM(amount) as daily_earnings,
                COUNT(*) as daily_transactions
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Summary statistics
        $stats = [
            'total_earnings' => LedgerEntry::where('type', 'PARTNER_EARNING')->sum('amount'),
            'total_transactions' => LedgerEntry::where('type', 'PARTNER_EARNING')->count(),
            'platform_revenue' => LedgerEntry::where('type', 'PLATFORM_FEE')->sum('amount'),
            'period_earnings' => LedgerEntry::where('type', 'PARTNER_EARNING')
                ->where('created_at', '>=', $startDate)->sum('amount'),
            'period_transactions' => LedgerEntry::where('type', 'PARTNER_EARNING')
                ->where('created_at', '>=', $startDate)->count(),
        ];

        return view('admin.earnings.dashboard', compact('recentEarnings', 'stats', 'days'));
    }

    /**
     * Show partner-specific earnings.
     */
    public function partnerEarnings(Request $request, $partnerId): View
    {
        $partner = User::with('partnerProfile')->findOrFail($partnerId);

        $earnings = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->with('order')
            ->latest()
            ->paginate(20);

        $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

        // Real total earned: online earnings + COD receipts minus the platform
        // cut (COD commission + reversals) - matches PartnerBalance.recalculateForPartner
        $totalEarned = LedgerEntry::where('user_id', $partnerId)
                ->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->whereIn('type', ['PAS_COMMISSION', 'EARNING_REVERSAL'])
                ->sum('amount');

        $summary = [
            'total_earnings' => $earnings->sum('amount'),
            'total_transactions' => $earnings->count(),
            'available_balance' => $partnerBalance?->available_balance ?? 0,
            'total_earned' => $totalEarned,
        ];

        return view('admin.earnings.partner', compact('partner', 'earnings', 'summary'));
    }

    // === COMMISSION RULES MANAGEMENT ===

    /**
     * Display commission rules.
     */
    public function commissionRules(Request $request): View
    {
        $query = CommissionRule::with('service');

        // Filter by service
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        $rules = $query->latest()->paginate(15);
        $services = Service::all();

        return view('admin.earnings.commission-rules', compact('rules', 'services'));
    }

    /**
     * Show form to create commission rule.
     */
    public function createCommissionRule(): View
    {
        $services = Service::all();
        return view('admin.earnings.create-commission-rule', compact('services'));
    }

    /**
     * Store new commission rule.
     */
    public function storeCommissionRule(Request $request): RedirectResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'city' => 'required|string|max:255',
            'slab_start' => 'required|numeric|min:0',
            'slab_end' => 'required|numeric|gt:slab_start',
            'partner_pct' => 'required|numeric|min:0|max:100',
            'platform_pct' => 'required|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        // Validate that percentages add up to 100
        if ($request->partner_pct + $request->platform_pct != 100) {
            return back()->withErrors(['partner_pct' => 'Partner and platform percentages must add up to 100%']);
        }

        CommissionRule::create($request->all());

        return redirect()->route('admin.earnings.commission-rules')
            ->with('success', 'Commission rule created successfully.');
    }

    /**
     * Show form to edit commission rule.
     */
    public function editCommissionRule($ruleId): View
    {
        $rule = CommissionRule::findOrFail($ruleId);
        $services = Service::all();

        return view('admin.earnings.commission-rules-edit', compact('rule', 'services'));
    }

    /**
     * Update commission rule.
     */
    public function updateCommissionRule(Request $request, $ruleId): RedirectResponse
    {
        $rule = CommissionRule::findOrFail($ruleId);

        $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'city' => 'required|string|max:255',
            'slab_start' => 'required|numeric|min:0',
            'slab_end' => 'required|numeric|gt:slab_start',
            'partner_pct' => 'required|numeric|min:0|max:100',
            'platform_pct' => 'required|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after:valid_from',
        ]);

        // Validate that percentages add up to 100
        if ($request->partner_pct + $request->platform_pct != 100) {
            return back()->withErrors(['partner_pct' => 'Partner and platform percentages must add up to 100%']);
        }

        $rule->update($request->all());

        return redirect()->route('admin.earnings.commission-rules')
            ->with('success', 'Commission rule updated successfully.');
    }

    /**
     * Delete commission rule.
     */
    public function destroyCommissionRule($ruleId): RedirectResponse
    {
        $rule = CommissionRule::findOrFail($ruleId);
        $rule->delete();

        return redirect()->route('admin.earnings.commission-rules')
            ->with('success', 'Commission rule deleted successfully.');
    }

    // === PARTNER BALANCE MANAGEMENT ===

    /**
     * Display partner balances.
     */
    public function balances(Request $request): View
    {
        $query = PartnerBalance::with('partner.user');

        if ($request->filled('search')) {
            $query->whereHas('partner.user', function ($q) use ($request) {
                $q->where('name', SchemaCompat::likeOperator(), '%' . $request->search . '%');
            });
        }

        $balances = $query->latest('last_updated')->paginate(15);

        // Enrich with live pending payout amounts (real semantics)
        $pendingByPartner = \App\Models\PartnerPayout::whereIn('status', ['pending', 'processing'])
            ->selectRaw('partner_id, SUM(amount) as total')
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        foreach ($balances as $balance) {
            $balance->pending_balance = (float) ($pendingByPartner[$balance->partner_id] ?? 0);
        }

        return view('admin.earnings.balances', compact('balances'));
    }

    /**
     * Recalculate balance for specific partner.
     */
    public function recalculateBalance(Request $request, $partnerId): RedirectResponse
    {
        try {
            $balance = PartnerBalance::recalculateForPartner($partnerId);

            return back()->with('success', 'Partner balance recalculated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate balance: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate all partner balances.
     */
    public function recalculateAllBalances(Request $request): RedirectResponse
    {
        try {
            $partnerIds = \App\Models\PartnerProfile::pluck('partner_id');

            foreach ($partnerIds as $partnerId) {
                PartnerBalance::recalculateForPartner($partnerId);
            }

            return back()->with('success', 'All partner balances recalculated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate balances: ' . $e->getMessage());
        }
    }

    /**
     * Bulk recalculate selected earnings.
     */
    public function bulkRecalculate(Request $request): RedirectResponse
    {
        $request->validate([
            'partner_ids' => 'required|array',
            'partner_ids.*' => 'exists:partner_profiles,partner_id',
        ]);

        try {
            foreach ($request->partner_ids as $partnerId) {
                PartnerBalance::recalculateForPartner($partnerId);
            }

            return back()->with('success', 'Selected partner balances recalculated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate balances: ' . $e->getMessage());
        }
    }

    /**
     * Show individual earning details.
     */
    public function show($entryId): View
    {
        $earning = LedgerEntry::with(['user', 'order'])->findOrFail($entryId);

        return view('admin.earnings.show', compact('earning'));
    }
}
