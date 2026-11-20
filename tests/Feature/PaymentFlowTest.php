<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Address;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\PaymentService;

/**
 * End-to-end verification of the Pay After Service (PAS) payment flow.
 *
 * Covers: PAS booking confirmation, the prepay gate, cash settlement with
 * customer confirmation (ledger posting), idempotent confirmations, the
 * "online declared but unpaid" block, disputes, and the late-payment
 * auto-refund.
 */
class PaymentFlowTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partner;
    private Address $address;
    private Service $service;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

        // The platform account (config('payment.platform_user_id')) owns the
        // fee / tax / discount legs, so it must exist for the ledger FK.
        User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'System',
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));
        $this->syncUserIdSequence();

        $this->customer = User::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Test',
            'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);

        PartnerProfile::unguarded(function () {
            PartnerProfile::create([
                'partner_id' => $this->partner->user_id,
                'status' => 'active',
            ]);
        });

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);

        $category = Category::create([
            'name' => 'Cleaning',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        ServicePricing::create([
            'category_id' => $category->category_id,
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        // The preferred partner must offer the requested service.
        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function bookingPayload(string $paymentMethod = 'pas'): array
    {
        return [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => $paymentMethod,
        ];
    }

    private function createOrder(string $paymentMethod = 'pas'): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', $this->bookingPayload($paymentMethod));
        $response->assertStatus(201);
        return Order::findOrFail($response->json('order_id'));
    }

    private function partnerAcceptsAndStarts(Order $order): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    private function addCompletionDocument(Order $order): void
    {
        $request = $order->serviceRequests()->first();
        ServiceCompletionDocument::unguarded(function () use ($request) {
            ServiceCompletionDocument::create([
                'service_request_id' => $request->request_id,
                'partner_id' => $this->partner->user_id,
                'uploaded_by' => $this->partner->user_id,
                'document_type' => 'after_photo',
                'original_filename' => 'after.jpg',
                'stored_filename' => 'after.jpg',
                'file_path' => 'completions/test/after.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'file_hash' => 'abc123',
                'access_token' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        });
    }

    private function partnerCompletesWithCash(Order $order): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_pas_booking_is_confirmed_immediately(): void
    {
        $order = $this->createOrder('pas');

        // PAS is never blocked at booking: the order is confirmed (auto-assigned
        // to the preferred partner) immediately, with no pending_payment gate.
        $this->assertSame('partner_assigned', $order->fresh()->status);
        $this->assertNotContains($order->fresh()->status, ['created', 'pending_payment']);
        $this->assertSame('pas', $order->fresh()->payment_method);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertFalse((bool) $order->fresh()->cash_confirmed);
    }

    public function test_pas_cash_completion_posts_ledger_immediately(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_received);
        $this->assertFalse((bool) $order->cash_confirmed);

        // The ledger is built at completion and follows the promo rule: the
        // partner earns their share of the GROSS line (1500 -> 1200), holds the
        // cash the customer actually paid (1695), and the platform books its
        // fee (300) and the VAT (195) - 1200 + 300 + 195 = 1695.
        $rows = LedgerEntry::where('booking_id', $order->order_id)->get()->keyBy('type');
        $this->assertEqualsCanonicalizing(
            ['PARTNER_EARNING', 'PAS_CASH_SETTLEMENT', 'PLATFORM_FEE', 'TAX_PAYABLE'],
            $rows->keys()->all(),
            'Completion must post the earning, the cash leg and the platform legs'
        );
        $this->assertEqualsWithDelta(1200.00, (float) $rows['PARTNER_EARNING']->amount, 0.01, 'Partner earns 80% of the gross line');
        $this->assertEqualsWithDelta(-1695.00, (float) $rows['PAS_CASH_SETTLEMENT']->amount, 0.01, 'Cash leg must be exactly the order total');
        $this->assertEqualsWithDelta(300.00, (float) $rows['PLATFORM_FEE']->amount, 0.01);
        $this->assertEqualsWithDelta(195.00, (float) $rows['TAX_PAYABLE']->amount, 0.01);
        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());
    }

    public function test_customer_confirmation_verifies_without_duplicating_ledger(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertTrue((bool) $order->cash_confirmed);
        $this->assertNotNull($order->cash_confirmed_at);

        // Confirmation is verification only - the ledger must not grow.
        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());

        // A second confirmation must be idempotent and must not double-post.
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Cash payment already confirmed');
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());
        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());
    }

    public function test_prepaid_order_blocks_accept_and_start_work_until_paid(): void
    {
        $order = $this->createOrder('online');

        // Prepaid online bookings are NOT live before the gateway money lands:
        // the order stays 'created' (unpaid) even with a preferred partner.
        $this->assertSame('created', $order->fresh()->status);

        // The partner cannot accept an unpaid prepaid booking (payment gate).
        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('payment_required', true);

        // Gateway money lands (webhook effect): order promoted to partner_assigned
        // and the preferred-partner request promoted from pending to assigned.
        $order->update(['payment_status' => 'paid', 'status' => 'partner_assigned']);
        $order->serviceRequests()->update([
            'status' => 'assigned',
            'partner_assigned_at' => now(),
            'assignment_expires_at' => now()->addMinutes(5),
        ]);

        // The partner accepts and starts work - now allowed because it's paid.
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_prepaid_completion_credits_partner_once(): void
    {
        $order = $this->createOrder('online');

        // Simulate gateway-paid before the job starts. The settlement promotes
        // the preferred-partner request to assigned alongside the order.
        $order->update(['payment_status' => 'paid']);
        $order->serviceRequests()->update([
            'status' => 'assigned',
            'partner_assigned_at' => now(),
            'assignment_expires_at' => now()->addMinutes(5),
        ]);

        $this->partnerAcceptsAndStarts($order);

        $this->addCompletionDocument($order);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'online',
        ])->assertStatus(200)->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('online', $order->payment_received);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());

        // Completion is once-only: the status guard rejects a second complete.
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'online',
        ])->assertStatus(400);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());
    }

    public function test_pas_online_declaration_without_payment_is_blocked(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);

        // Declaring "online" while the gateway payment hasn't landed must be blocked.
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'online',
        ])->assertStatus(422)->assertJsonPath('payment_required', true);

        // The job must remain in progress, not completed.
        $order->refresh();
        $this->assertSame('working', $order->serviceRequests()->first()->status);
        $this->assertNotSame('completed', $order->status);

        // Once the gateway money arrives the completion goes through.
        $order->update(['payment_status' => 'paid']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'online',
        ])->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());
    }

    public function test_cash_dispute_blocks_confirmation_and_reverses_ledger(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        // The completion posted the settlement...
        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/dispute-cash", [
            'reason' => 'I paid online, not cash',
        ])->assertStatus(200)->assertJsonPath('success', true);

        $order->refresh();
        $this->assertTrue((bool) $order->cash_disputed);
        $this->assertNull($order->cash_confirmed_at);

        // Confirming a disputed cash settlement must be refused.
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(409);

        // The dispute reversed the partner's side: the earning is clawed back
        // and the cash they were holding stops counting against them, so the
        // partner nets zero for this job.
        $earningNet = LedgerEntry::where('booking_id', $order->order_id)
            ->whereIn('type', ['PARTNER_EARNING', 'EARNING_REVERSAL'])
            ->get()
            ->sum(fn ($entry) => LedgerEntry::signedAmount($entry->type, (float) $entry->amount));
        $this->assertSame(0.0, (float) $earningNet, 'Disputed job must net zero earnings');
        $this->assertSame(0.0, (float) LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->sum('amount'), 'The cash the partner was holding must be cleared');
        $this->assertSame(6, LedgerEntry::where('booking_id', $order->order_id)->count());
    }

    public function test_earnings_endpoint_returns_the_built_ledger(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        // The partner sees the earnings immediately after completion, even
        // though the customer has not confirmed the cash handover yet.
        Sanctum::actingAs($this->partner, ['*']);
        $response = $this->getJson('/api/v1/earnings');
        $response->assertStatus(200)->assertJsonPath('success', true);

        $entries = collect($response->json('data.earnings.data'))
            ->where('booking_id', $order->order_id)
            ->pluck('type')
            ->all();
        $this->assertContains('PARTNER_EARNING', $entries);
        $this->assertContains('PAS_CASH_SETTLEMENT', $entries);

        // The row tells the partner what the customer paid and what the
        // platform kept - the split is never a mystery.
        $earningRow = collect($response->json('data.earnings.data'))
            ->firstWhere('type', 'PARTNER_EARNING');
        $this->assertEqualsWithDelta(1695.00, (float) $earningRow['cash_collected'], 0.01);
        $this->assertEqualsWithDelta(495.00, (float) $earningRow['platform_cut'], 0.01);

        // Lifetime stats must be the NET total (receipt - commission).
        $this->assertSame(
            0,
            abs((float) $response->json('data.stats.total_earnings') - 1200.00) < 0.01 ? 0 : 1,
            'Stats total_earnings should be the net partner share (1200), got: ' . var_export($response->json('data.stats'), true)
        );

        // Balance endpoint must show the same net total.
        $balance = $this->getJson('/api/v1/payouts/balance');
        $balance->assertStatus(200);
        $this->assertSame(
            0,
            abs((float) $balance->json('data.total_earnings') - 1200.00) < 0.01 ? 0 : 1,
            'Cached total_earnings should be the net partner share (1200)'
        );
    }

    public function test_negative_partner_balance_is_exposed_not_clamped(): void
    {
        // Force a balance sheet where the partner owes the platform:
        // a small PAS cash job confirms a commission that exceeds the
        // partner's cached balance (already negative from prior deductions).
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(200);

        Sanctum::actingAs($this->partner, ['*']);
        $balance = $this->getJson('/api/v1/payouts/balance');
        $balance->assertStatus(200);

        $available = (float) $balance->json('data.available_balance');
        $this->assertLessThan(
            0,
            $available,
            'Available balance must stay negative when the partner owes the platform (found ' . $available . ')'
        );

        // The stored balance must also be negative (not clamped at the source).
        $this->assertLessThan(0, (float) $balance->json('data.stored_balance'));
    }

    public function test_pas_is_rejected_for_prepay_services(): void
    {
        // Mark the service as prepay-required.
        $this->service->update(['requires_prepay' => true]);

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/bookings', $this->bookingPayload('pas'))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // The order must not have been created.
        $this->assertSame(0, Order::count());
    }

    public function test_late_gateway_payment_after_cash_settlement_is_auto_refunded(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);
        $this->partnerCompletesWithCash($order);

        // A stale gateway session lands (webhook) after the cash settlement.
        $payment = Payment::unguarded(fn () => Payment::create([
            'booking_id' => $order->order_id,
            'gateway' => 'esewa',
            'txn_id' => 'STALE-TXN-1',
            'amount' => $order->total_amount,
            'status' => 'initiated',
        ]));

        // The gateway confirms the payment.
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-STALE-1',
                'total_amount' => (string) $order->total_amount,
            ], 200),
        ]);

        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => 'STALE-TXN-1',
            'total_amount' => $order->total_amount,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['refunded']);

        // The payment is refunded and the cash settlement stays untouched.
        $this->assertSame('refunded', $payment->refresh()->status);
        $order->refresh();
        $this->assertSame('cash', $order->payment_received);
        $this->assertSame('completed', $order->status);
        $this->assertFalse((bool) $order->cash_confirmed);

        // No double-charge: the late gateway landing must not add entries on
        // top of the completion-time settlement (earning, cash, fee, tax).
        $this->assertSame(4, LedgerEntry::where('booking_id', $order->order_id)->count());
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)
            ->whereNotIn('type', ['PARTNER_EARNING', 'PAS_CASH_SETTLEMENT', 'PLATFORM_FEE', 'TAX_PAYABLE'])
            ->count());
    }
}
