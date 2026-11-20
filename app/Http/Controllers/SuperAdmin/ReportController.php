<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one Reports area the admin panel was missing. Every report reads the
 * ledger / notification log the rest of the panel already uses, takes a date
 * range, and can be exported as a streamed CSV with a totals row.
 */
class ReportController extends Controller
{
    /** The same two types the dashboard counts as net revenue. */
    private const REVENUE_TYPES = ['PLATFORM_FEE', 'TAX_PAYABLE'];

    /** Promo spend: what the platform paid to discount orders. */
    private const DISCOUNT_TYPE = 'DISCOUNT_EXPENSE';

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('admin.reports.index', compact('from', 'to'));
    }

    public function revenue(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $rows = $this->revenueRows($from, $to);

        return view('admin.reports.revenue', compact('rows', 'from', 'to'));
    }

    public function revenueCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->revenueRows($from, $to);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ledger type', 'Entries', 'Amount (NPR)']);

            foreach ($rows as $row) {
                fputcsv($out, [$row->type, $row->entries, number_format((float) $row->total, 2, '.', '')]);
            }

            // Promo discounts are platform spend, so they are reported apart
            // from revenue instead of being folded into a single total.
            $revenueRows = $rows->whereIn('type', self::REVENUE_TYPES);
            $discountRows = $rows->where('type', self::DISCOUNT_TYPE);
            $revenue = (float) $revenueRows->sum('total');
            $discounts = (float) $discountRows->sum('total');

            fputcsv($out, ['', '', '']);
            fputcsv($out, ['Revenue (fee + tax)', $revenueRows->sum('entries'), number_format($revenue, 2, '.', '')]);
            fputcsv($out, ['Promo discounts funded', $discountRows->sum('entries'), number_format($discounts, 2, '.', '')]);
            fputcsv($out, ['Net revenue', '', number_format($revenue - $discounts, 2, '.', '')]);

            fclose($out);
        }, $this->filename('revenue', $from, $to), ['Content-Type' => 'text/csv']);
    }

    public function delivery(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $rows = $this->deliveryRows($from, $to);

        return view('admin.reports.delivery', compact('rows', 'from', 'to'));
    }

    public function deliveryCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->deliveryRows($from, $to);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Status', 'Platform', 'Notifications']);

            foreach ($rows as $row) {
                fputcsv($out, [$row->status, $row->platform ?: 'unknown', $row->total]);
            }

            fputcsv($out, ['TOTAL', '', $rows->sum('total')]);

            fclose($out);
        }, $this->filename('notification-delivery', $from, $to), ['Content-Type' => 'text/csv']);
    }

    /**
     * Inclusive date range; defaults to the current month so a report is never
     * an unbounded table scan.
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $from = ! empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfMonth();

        $to = ! empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function revenueRows(Carbon $from, Carbon $to)
    {
        return DB::table('ledger_entries')
            ->selectRaw('type, COUNT(*) as entries, SUM(amount) as total')
            ->whereIn('type', array_merge(self::REVENUE_TYPES, [self::DISCOUNT_TYPE]))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type')
            ->orderBy('type')
            ->get();
    }

    private function deliveryRows(Carbon $from, Carbon $to)
    {
        return DB::table('notification_logs')
            ->selectRaw('status, platform, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status', 'platform')
            ->orderBy('status')
            ->orderBy('platform')
            ->get();
    }

    private function filename(string $name, Carbon $from, Carbon $to): string
    {
        return sprintf('%s_%s_%s.csv', $name, $from->format('Ymd'), $to->format('Ymd'));
    }
}
