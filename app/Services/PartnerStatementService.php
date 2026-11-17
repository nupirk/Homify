<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use App\Models\PartnerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class PartnerStatementService
{
    public function generateStatementData(int $partnerId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();

        $partner = User::with('partnerProfile')->findOrFail($partnerId);
        $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

        $ledgerEntries = LedgerEntry::where('user_id', $partnerId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('order')
            ->orderBy('created_at', 'asc')
            ->get();

        $allEntries = LedgerEntry::where('user_id', $partnerId)->get();

        return [
            'partner' => $partner,
            'period' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d')
            ],
            'current_balance' => $partnerBalance ? $partnerBalance->available_balance : 0,
            'period_summary' => $this->calculatePeriodSummary($ledgerEntries),
            'all_time_summary' => $this->calculateAllTimeSummary($allEntries),
            'payment_method_breakdown' => $this->calculatePaymentMethodBreakdown($partnerId, $dateFrom, $dateTo),
            'monthly_breakdown' => $this->calculateMonthlyBreakdown($partnerId, $dateFrom, $dateTo),
            'recent_transactions' => $ledgerEntries->take(50),
            'pas_transactions' => $this->getPASTransactions($partnerId, $dateFrom, $dateTo)
        ];
    }

    private function calculateAllTimeSummary(Collection $allEntries): array
    {
        return [
            'total_earnings' => $allEntries->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])->sum('amount'),
            'total_pas_commission' => $allEntries->where('type', 'PAS_COMMISSION')->sum('amount'),
            'total_cash_settled' => $allEntries->where('type', 'PAS_CASH_SETTLEMENT')->sum('amount'),
            'total_withdrawals' => $allEntries->where('type', 'WITHDRAWAL')->sum('amount'),
            'total_transactions' => $allEntries->count(),
            'first_transaction' => $allEntries->sortBy('created_at')->first()?->created_at,
            'last_transaction' => $allEntries->sortByDesc('created_at')->first()?->created_at
        ];
    }

    private function calculatePeriodSummary(Collection $ledgerEntries): array
    {
        return [
            'total_earnings' => $ledgerEntries->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])->sum('amount'),
            'total_pas_commission' => $ledgerEntries->where('type', 'PAS_COMMISSION')->sum('amount'),
            'total_cash_settled' => $ledgerEntries->where('type', 'PAS_CASH_SETTLEMENT')->sum('amount'),
            'total_withdrawals' => $ledgerEntries->where('type', 'WITHDRAWAL')->sum('amount'),
            'earnings_count' => $ledgerEntries->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])->count(),
            'pas_jobs_count' => $ledgerEntries->where('type', 'PAS_COMMISSION')->count(),
            'withdrawal_count' => $ledgerEntries->where('type', 'WITHDRAWAL')->count(),
            // MODEL A: net balance change - earnings minus the platform cut minus
            // the cash the partner already collected (settled on the spot)
            'net_earnings' => $ledgerEntries->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])->sum('amount')
                - $ledgerEntries->where('type', 'PAS_COMMISSION')->sum('amount')
                + $ledgerEntries->where('type', 'PAS_CASH_SETTLEMENT')->sum('amount')
        ];
    }

    private function calculatePaymentMethodBreakdown(int $partnerId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $pasEarnings = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_RECEIPT')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $pasCommissions = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_COMMISSION')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $pasCashSettled = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $onlineEarnings = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $pasJobsCount = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_COMMISSION')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $onlineJobsCount = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'pas' => [
                'earnings' => $pasEarnings,
                'commission_deducted' => $pasCommissions,
                'cash_settled' => $pasCashSettled,
                'net_earnings' => $pasEarnings - $pasCommissions + $pasCashSettled,
                'jobs_count' => $pasJobsCount,
                'avg_commission' => $pasJobsCount > 0 ? $pasCommissions / $pasJobsCount : 0
            ],
            'online' => [
                'earnings_received' => $onlineEarnings,
                'jobs_count' => $onlineJobsCount,
                'avg_earning' => $onlineJobsCount > 0 ? $onlineEarnings / $onlineJobsCount : 0
            ]
        ];
    }

    private function calculateMonthlyBreakdown(int $partnerId, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return LedgerEntry::where('user_id', $partnerId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                DATE_TRUNC(\'month\', created_at) as month,
                type,
                SUM(amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('month', 'type')
            ->orderBy('month', 'asc')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->month)->format('Y-m');
            });
    }

    private function getPASTransactions(int $partnerId, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        return LedgerEntry::where('user_id', $partnerId)
            ->whereIn('type', ['PAS_RECEIPT', 'PAS_COMMISSION', 'PAS_CASH_SETTLEMENT'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function generateStatementFilename(User $partner, Carbon $dateFrom, Carbon $dateTo): string
    {
        $partnerName = str_replace(' ', '_', $partner->first_name . '_' . $partner->last_name);
        $period = $dateFrom->format('Y-m-d') . '_to_' . $dateTo->format('Y-m-d');
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "partner_statement_{$partnerName}_{$period}_{$timestamp}.pdf";
    }
}
