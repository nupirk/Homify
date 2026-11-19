<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use App\Support\SchemaCompat;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PartnerProfile;
use App\Models\Order;
use App\Models\Service;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Review;
use App\Models\AuditLog;
use App\Models\ErrorLog;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('email', SchemaCompat::likeOperator(), $s)
                    ->orWhere('first_name', SchemaCompat::likeOperator(), $s)
                    ->orWhere('last_name', SchemaCompat::likeOperator(), $s)
                    ->orWhere('phone', SchemaCompat::likeOperator(), $s);
            });
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        $users = $query->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function index()
    {
        $stats = [
            'total_customers' => User::where('user_type', 'customer')->count(),
            'total_partners' => User::where('user_type', 'partner')->count(),
            'partners' => PartnerProfile::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'total_services' => Service::where('is_active', true)->count(),
            'total_categories' => Category::where('is_active', true)->count(),
            'recent_activity' => Order::whereDate('created_at', today())->count(),
            'total_revenue' => DB::table('ledger_entries')
                ->whereIn('type', ['PLATFORM_FEE', 'TAX_PAYABLE'])
                ->sum('amount'), // Sum platform fees and taxes (net revenue)
            'total_partner_earnings' => DB::table('ledger_entries')
                ->where('type', 'PARTNER_EARNING')
                ->sum('amount'),
            'total_withdrawals' => DB::table('ledger_entries')
                ->where('type', 'WITHDRAWAL')
                ->sum('amount'), // This should be negative amounts
            'average_rating' => Review::avg('rating_value'),
            // System health, so a failure is visible without opening a report page.
            'errors_24h' => ErrorLog::where('last_seen_at', '>=', now()->subDay())->sum('occurrences'),
            'error_sources_24h' => ErrorLog::where('last_seen_at', '>=', now()->subDay())->count(),
            'admin_logins_24h' => AuditLog::where('entity_type', 'admin_auth')
                ->where('action', 'admin_login')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'failed_logins_24h' => AuditLog::where('entity_type', 'admin_auth')
                ->where('action', 'admin_login_failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $userGrowth = $this->calculateUserGrowth();
        $revenueGrowth = $this->calculateRevenueGrowth();
        $orderTrends = $this->getOrderTrends();
        $topServices = $this->getTopServices();
        $recentOrders = $this->getRecentOrders();

        return view('admin.dashboard', compact(
            'stats',
            'userGrowth',
            'revenueGrowth',
            'orderTrends',
            'topServices',
            'recentOrders'
        ));
    }

    private function calculateUserGrowth()
    {
        $currentMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $lastMonth = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        if ($lastMonth == 0) return $currentMonth > 0 ? 100 : 0;

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100);
    }

    /**
     * Growth of the same figure the card shows: platform fees plus tax owed.
     * Reading it from `transactions` measured gross customer payments, so the
     * percentage described a different number from the total next to it.
     */
    private function calculateRevenueGrowth()
    {
        $revenueFor = function ($month) {
            return DB::table('ledger_entries')
                ->whereIn('type', ['PLATFORM_FEE', 'TAX_PAYABLE'])
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->sum('amount');
        };

        $currentMonth = $revenueFor(now());
        $lastMonth = $revenueFor(now()->subMonth());

        if ($lastMonth == 0) return $currentMonth > 0 ? 100 : 0;

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100);
    }

    private function getOrderTrends()
    {
        return Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [now()->subDays(30), now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getTopServices()
    {
        // `services.base_price` is null on every seeded service: the real
        // price lives on service_pricing, so the dashboard was printing a bare
        // "Rs" next to each top service.
        return Service::withCount(['orderItems as orders_count'])
            ->withMin(['pricing as pricing_min' => fn ($query) => $query->where('is_active', true)], 'base_price')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();
    }

    private function getRecentOrders()
    {
        return Order::with(['user', 'orderItems.service'])
            ->latest()
            ->limit(10)
            ->get();
    }
}
