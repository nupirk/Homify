<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\LedgerService;

class LedgerGuardTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private function makeUser(string $type = 'customer'): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => $type,
            'is_active' => true,
        ]);

        if ($type === 'partner') {
            PartnerProfile::unguarded(function () use ($user) {
                PartnerProfile::create([
                    'partner_id' => $user->user_id,
                    'status' => 'active',
                ]);
            });
        }

        return $user;
    }

    private function makeOrder(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'status' => 'completed',
            'payment_method' => 'pas',
            'subtotal' => 1000,
            'tax_amount' => 130,
            'platform_fee' => 200,
            'total_amount' => 1130,
            'final_payable' => 1130,
            'transaction_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
        ]);
    }

    public function test_record_refund_is_idempotent_per_booking(): void
    {
        $customer = $this->makeUser();
        $order = $this->makeOrder($customer->user_id);

        $service = app(LedgerService::class);
        $service->recordRefund($order->order_id, $customer->user_id, 1130, ['reason' => 'cancelled']);
        $service->recordRefund($order->order_id, $customer->user_id, 1130, ['reason' => 'double submit']);

        $this->assertSame(
            1,
            LedgerEntry::where('booking_id', $order->order_id)->where('type', 'REFUND')->count(),
            'A second refund for the same booking must be blocked'
        );
    }

    public function test_record_pas_receipt_is_idempotent_per_booking(): void
    {
        $customer = $this->makeUser();
        $partner = $this->makeUser('partner');
        $order = $this->makeOrder($customer->user_id);

        $service = app(LedgerService::class);
        $service->recordPASReceipt($partner->user_id, $order->order_id, 1130);
        $service->recordPASReceipt($partner->user_id, $order->order_id, 1130);

        $this->assertSame(
            1,
            LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PAS_RECEIPT')->count(),
            'A second PAS receipt for the same booking must be blocked'
        );

        // Partner must only be credited once
        $entries = LedgerEntry::where('user_id', $partner->user_id)
            ->where('type', 'PAS_RECEIPT')
            ->get();
        $this->assertSame(1130.0, (float) $entries->sum('amount'));
    }

    public function test_refund_and_pas_receipt_can_coexist(): void
    {
        $customer = $this->makeUser();
        $partner = $this->makeUser('partner');
        $order = $this->makeOrder($customer->user_id);

        $service = app(LedgerService::class);
        $service->recordPASReceipt($partner->user_id, $order->order_id, 1130);
        $service->recordRefund($order->order_id, $customer->user_id, 1130, ['reason' => 'test']);

        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'REFUND')->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PAS_RECEIPT')->count());
    }
}
