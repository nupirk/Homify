<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Order;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Log;

/**
 * The one calculator for a Pay-After-Service job settled in cash.
 *
 * Money rules (see PromoService / MembershipService for the order side):
 *  - The customer pays `orders.total_amount`: the discounted, taxed figure
 *    they saw at checkout and confirm in the app.
 *  - The partner is paid their commission share of the GROSS service value. A
 *    promo or member discount is platform marketing spend and never comes out
 *    of the partner's pocket, so a heavily discounted job can leave the
 *    platform owing the partner.
 *  - The platform's cut is measured on the gross value too, with the discount
 *    posted as its own DISCOUNT_EXPENSE line, so every cash job reconciles:
 *
 *        total_amount = partner_earnings + platform_fee + tax - discount
 *
 * The partner-entered "final amount" is deliberately ignored: the order is the
 * price the customer agreed to, and letting the payee declare what they
 * collected meant the customer confirmed a different number than was settled.
 */
class PasSettlementService
{
    /**
     * Settle every completed partner line of a cash-settled PAS order.
     */
    public function settle(Order $order): void
    {
        $lines = $order->serviceRequests()
            ->with('orderItem')
            ->where('status', 'completed')
            ->whereNotNull('partner_id')
            ->get();

        if ($lines->isEmpty()) {
            Log::warning('PAS settlement skipped: no completed partner lines', [
                'order_id' => $order->order_id,
            ]);

            return;
        }

        $grossTotal = round($lines->sum(fn (ServiceRequest $sr) => $this->grossFor($sr)), 2);
        $cashTotal = round((float) $order->total_amount, 2);

        /** @var array<int, array{gross: float, cash: float, earning: float, service_id: int|null}> $perPartner */
        $perPartner = [];

        foreach ($lines as $line) {
            $gross = $this->grossFor($line);
            $serviceId = $line->orderItem?->service_id;
            $earning = $this->earningFor($serviceId, $gross);
            // Cash is split by the gross value of the lines, which is also the
            // basis of each partner's earning.
            $share = $grossTotal > 0 ? $gross / $grossTotal : 0;

            $partnerId = (int) $line->partner_id;
            $perPartner[$partnerId] ??= ['gross' => 0.0, 'cash' => 0.0, 'earning' => 0.0, 'service_id' => $serviceId];

            $perPartner[$partnerId]['gross'] += $gross;
            $perPartner[$partnerId]['cash'] += round($cashTotal * $share, 2);
            $perPartner[$partnerId]['earning'] += $earning;
        }

        // Rounding drift on multi-partner orders: the last partner absorbs the
        // paisa difference so the cash legs always add up to total_amount.
        $allocated = round(array_sum(array_column($perPartner, 'cash')), 2);
        if (abs($allocated - $cashTotal) >= 0.01 && $perPartner !== []) {
            $lastKey = array_key_last($perPartner);
            $perPartner[$lastKey]['cash'] = round($perPartner[$lastKey]['cash'] + ($cashTotal - $allocated), 2);
        }

        $ledger = app(LedgerService::class);
        $earningsTotal = 0.0;

        foreach ($perPartner as $partnerId => $row) {
            $ledger->recordPasPartnerSettlement(
                $order,
                (int) $partnerId,
                round($row['gross'], 2),
                round($row['cash'], 2),
                round($row['earning'], 2),
                $row['service_id']
            );

            $earningsTotal += round($row['earning'], 2);
        }

        $ledger->recordPasPlatformLegs($order, round($earningsTotal, 2));

        Log::info('PAS order settled', [
            'order_id' => $order->order_id,
            'cash_total' => $cashTotal,
            'partner_earnings' => round($earningsTotal, 2),
            'discount' => round((float) $order->discount_amount, 2),
            'partners' => array_keys($perPartner),
        ]);
    }

    /**
     * What the line is worth before any discount - the number the partner's
     * share is calculated from.
     */
    private function grossFor(ServiceRequest $request): float
    {
        return round((float) ($request->orderItem->total_price ?? 0), 2);
    }

    /**
     * Partner share of a gross line value, using the applicable commission rule
     * with the same 80% fallback the online path uses.
     */
    private function earningFor(?int $serviceId, float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        if ($serviceId) {
            $rule = CommissionRule::findApplicableRule($serviceId, null, $gross, new \DateTime());

            if ($rule) {
                return round((float) $rule->calculateShares($gross)['partner_share'], 2);
            }
        }

        return round($gross * 0.80, 2);
    }
}
