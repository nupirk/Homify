<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use App\Services\PromoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reconciliation of the promo engine.
 *
 * Answers "how much did each code actually take off, and how much should it
 * have taken off". It never writes: run it on a live host to see where a code
 * was applied with no discount, where the stored amount disagrees with the
 * code's own rules, and where the usage counters drifted from real orders.
 *
 *   php artisan promo:audit
 *   php artisan promo:audit --code=WELCOME20
 */
class PromoAuditCommand extends Command
{
    protected $signature = 'promo:audit {--code= : Only audit one promo code}';

    protected $description = 'Report what every promo code gave away, and flag orders where the discount is missing or wrong';

    public function handle(): int
    {
        $only = $this->option('code');

        $promotions = Promotion::with('service')
            ->when($only, fn ($query, $code) => $query->whereRaw(
                'UPPER(promo_code) = ?',
                [strtoupper(trim((string) $code))]
            ))
            ->orderBy('promotion_id')
            ->get();

        if ($promotions->isEmpty()) {
            $this->warn('No promotions found.');

            return self::SUCCESS;
        }

        $summary = [];
        $exceptions = [];
        $givenTotal = 0.0;
        $expectedTotal = 0.0;

        foreach ($promotions as $promotion) {
            $orders = DB::table('orders')
                ->whereRaw('TRIM(promo_code) = ?', [$promotion->promo_code])
                ->orderByDesc('created_at')
                ->get([
                    'order_id', 'subtotal', 'discount_amount', 'membership_discount',
                    'tax_amount', 'total_amount', 'status', 'payment_status',
                    'payment_received', 'created_at',
                ]);

            $redemptions = DB::table('user_promotions')
                ->where('promotion_id', $promotion->promotion_id)
                ->where('used', true)
                ->count();

            $gross = 0.0;
            $given = 0.0;
            $expected = 0.0;
            // A cancelled order never consumed the code (releaseUsage hands it
            // back), so only live orders count towards the usage counter.
            $liveOrders = 0;

            foreach ($orders as $order) {
                $storedPromo = (float) $order->discount_amount - (float) ($order->membership_discount ?? 0);
                $base = $this->eligibleBase($promotion, $order);
                $shouldBe = PromoService::discountFor($promotion, $base);

                $gross += (float) $order->subtotal;
                $given += $storedPromo;
                $expected += $shouldBe;

                $cancelled = str_starts_with((string) $order->status, 'cancelled');
                if (! $cancelled) {
                    $liveOrders++;
                }

                if ($storedPromo <= 0 && $shouldBe > 0) {
                    $exceptions[] = [
                        $order->order_id,
                        $promotion->promo_code,
                        sprintf('Rs. 0 taken off - should be Rs. %s', number_format($shouldBe, 2)),
                    ];
                } elseif (abs($storedPromo - $shouldBe) > 0.01) {
                    $exceptions[] = [
                        $order->order_id,
                        $promotion->promo_code,
                        sprintf(
                            'stored Rs. %s vs expected Rs. %s',
                            number_format($storedPromo, 2),
                            number_format($shouldBe, 2)
                        ),
                    ];
                }
            }

            if ($liveOrders !== (int) $promotion->usage_count) {
                $exceptions[] = [
                    '—',
                    $promotion->promo_code,
                    sprintf(
                        'usage_count is %d but %d live orders carry the code',
                        (int) $promotion->usage_count,
                        $liveOrders
                    ),
                ];
            }

            $givenTotal += $given;
            $expectedTotal += $expected;

            $summary[] = [
                $promotion->promo_code,
                $this->describeDiscount($promotion),
                $promotion->service?->name ?? 'All services',
                $this->describeState($promotion),
                sprintf('%d/%s', (int) $promotion->usage_count, $promotion->usage_limit ? (int) $promotion->usage_limit : '∞'),
                $redemptions,
                $orders->count(),
                number_format($gross, 2),
                number_format($given, 2),
                number_format($expected, 2),
                number_format($expected - $given, 2),
            ];
        }

        $this->info('Promo audit — read only, nothing was changed.');
        $this->newLine();

        $this->table(
            ['Code', 'Discount', 'Scope', 'State', 'Uses', 'Redemptions', 'Orders', 'Gross', 'Given', 'Expected', 'Short'],
            $summary
        );

        if ($exceptions !== []) {
            $this->newLine();
            $this->warn('Orders that do not match their code:');
            $this->table(['Order', 'Code', 'Problem'], $exceptions);
        } else {
            $this->newLine();
            $this->info('Every order matches its code.');
        }

        $this->newLine();
        $this->line(sprintf(
            'Totals: Rs. %s given / Rs. %s should have been given / Rs. %s short',
            number_format($givenTotal, 2),
            number_format($expectedTotal, 2),
            number_format($expectedTotal - $givenTotal, 2)
        ));

        return self::SUCCESS;
    }

    /**
     * The discount base for one order: the whole subtotal for an unscoped
     * code, or only the matching line for a service-scoped one.
     */
    private function eligibleBase(Promotion $promotion, object $order): float
    {
        if (! $promotion->service_id) {
            return (float) $order->subtotal;
        }

        $lineTotal = DB::table('order_items')
            ->where('order_id', $order->order_id)
            ->where('service_id', $promotion->service_id)
            ->sum('total_price');

        return (float) $lineTotal;
    }

    private function describeDiscount(Promotion $promotion): string
    {
        if ($promotion->discount_percent !== null) {
            return rtrim(rtrim(number_format((float) $promotion->discount_percent, 2, '.', ''), '0'), '.') . '%';
        }

        if ($promotion->discount_amount !== null) {
            return 'Rs. ' . number_format((float) $promotion->discount_amount, 2);
        }

        return 'none';
    }

    private function describeState(Promotion $promotion): string
    {
        if (! $promotion->is_active) {
            return 'inactive';
        }
        if ($promotion->valid_from && $promotion->valid_from->isFuture()) {
            return 'scheduled';
        }
        if ($promotion->valid_until && $promotion->valid_until->isPast()) {
            return 'expired';
        }

        return 'live';
    }
}
