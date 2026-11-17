<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\SchemaCompat;
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
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CancellationService;
use App\Services\PaymentService;

/**
 * Phase 2/3 regression suite: ledger refund semantics, per-SR multi-partner
 * settlement split, and the payment amount-mismatch guard.
 */
class LedgerSettlementTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partnerA;
    private User $partnerB;
    private Address $address;
    private Service $serviceA;
    private Service $serviceB;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

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
            'first_name' => 'Test', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partnerA = $this->makePartner();
        $this->partnerB = $this->makePartner();

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street', 'city' => 'Kathmandu',
            'state' => 'Bagmati', 'postal_code' => '44600',
            'country' => 'Nepal', 'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

        $this->serviceA = $this->makeService($category->category_id, 'Home Cleaning', 1500);
        $this->serviceB = $this->makeService($category->category_id, 'Deep Cleaning', 2500);

        $this->partnerA->partnerProfile->services()->attach($this->serviceA->service_id);
        $this->partnerB->partnerProfile->services()->attach($this->serviceB->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function makePartner(): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $user->user_id,
            'status' => 'active',
        ]));
        return $user;
    }

    private function makeService(int $categoryId, string $name, float $price): Service
    {
        $service = Service::create([
            'category_id' => $categoryId, 'name' => $name,
            'description' => $name, 'is_active' => true, 'requires_prepay' => false,
        ]);
        ServicePricing::create([
            'category_id' => $categoryId, 'service_id' => $service->service_id,
            'tier_name' => 'standard', 'base_price' => $price, 'is_active' => true,
        ]);
        return $service;
    }

    private function createOnlineOrder(array $services): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => $services,
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'online',
        ]);
        $response->assertStatus(201);
        return Order::findOrFail($response->json('order_id'));
    }

    private function payOrder(Order $order): void
    {
        $payment = Payment::where('booking_id', $order->order_id)->first();
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-' . $order->order_id,
                'total_amount' => number_format((float) $order->total_amount, 2, '.', ''),
            ], 200),
        ]);
        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertTrue($result['success']);
    }

    private function addCompletionDocument(int $requestId, int $partnerId): void
    {
        $filename = "after-{$requestId}.jpg";
        ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
            'service_request_id' => $requestId,
            'partner_id' => $partnerId,
            'uploaded_by' => $partnerId,
            'document_type' => 'after_photo',
            'original_filename' => $filename,
            'stored_filename' => $filename,
            'file_path' => "completions/test/{$filename}",
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'file_hash' => md5($filename),
            'access_token' => \Illuminate\Support\Str::uuid()->toString(),
        ]));
    }

    public function test_amount_mismatch_is_refused_and_order_not_promoted(): void
    {
        $order = $this->createOnlineOrder([
            ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
        ]);
        $payment = Payment::where('booking_id', $order->order_id)->first();

        // Gateway returns a DIFFERENT amount than what the customer was
        // charged. The verification must refuse to settle - the payment stays
        // initiated and the order is never promoted to a live status.
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-X',
                'total_amount' => number_format((float) $order->total_amount - 100, 2, '.', ''),
            ], 200),
        ]);

        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('initiated', $payment->refresh()->status);
        $this->assertSame('created', $order->refresh()->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)->count());
    }

    public function test_refund_posts_customer_refund_and_reverses_platform_entries(): void
    {
        $order = $this->createOnlineOrder([
            ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
        ]);
        $this->payOrder($order);
        $this->assertSame('paid', $order->refresh()->payment_status);

        // Platform-side entries posted at payment time.
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'ESCROW_CREDIT')->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PLATFORM_FEE')->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'TAX_PAYABLE')->count());

        $service = app(CancellationService::class);
        $result = $service->cancel($order, 'customer', 'Changed my mind');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['refunded']);
        $this->assertTrue($result['refund_required']);

        $order->refresh();
        $this->assertSame('cancelled_by_customer', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertStringContainsString('REFUND REQUIRED', $order->admin_notes);

        // Customer REFUND row: money back INTO the customer's wallet.
        $refund = LedgerEntry::where('booking_id', $order->order_id)
            ->where('user_id', $this->customer->user_id)
            ->where('type', 'REFUND')
            ->first();
        $this->assertNotNull($refund);
        $this->assertEquals((float) $order->total_amount, (float) $refund->amount);

        // The customer's wallet nets back to zero (paid - refunded).
        $this->assertEqualsWithDelta(0.0, LedgerEntry::getUserBalance($this->customer->user_id), 0.01);

        // Every platform-side entry is reversed with dispute_reversal metadata.
        $reversals = LedgerEntry::where('booking_id', $order->order_id)
            ->whereIn('type', ['ESCROW_CREDIT', 'PLATFORM_FEE', 'TAX_PAYABLE'])
            ->whereRaw(SchemaCompat::jsonFlagExpression('metadata', 'dispute_reversal') . " = 'true'")
            ->get();
        $this->assertSame(3, $reversals->count());
        foreach ($reversals as $reversal) {
            $this->assertLessThan(0, (float) $reversal->amount, 'Reversal must be negative');
        }

        // A second cancel is refused - no duplicate refund.
        $again = $service->cancel($order, 'admin', 'Again');
        $this->assertFalse($again['success']);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'REFUND')->count());
    }

    public function test_multi_partner_order_splits_earnings_per_sr_line(): void
    {
        // Two lines, two partners - each completes their own SR.
        $order = $this->createOnlineOrder([
            ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
            ['service_id' => $this->serviceB->service_id, 'quantity' => 1],
        ]);
        $this->payOrder($order);

        $srA = ServiceRequest::where('order_id', $order->order_id)
            ->where('order_item_id', $order->items()->where('service_id', $this->serviceA->service_id)->first()->item_id)
            ->first();
        $srB = ServiceRequest::where('order_id', $order->order_id)
            ->where('order_item_id', $order->items()->where('service_id', $this->serviceB->service_id)->first()->item_id)
            ->first();

        $srA->update(['partner_id' => $this->partnerA->user_id, 'status' => 'in_progress', 'partner_assigned_at' => now()]);
        $srB->update(['partner_id' => $this->partnerB->user_id, 'status' => 'in_progress', 'partner_assigned_at' => now()]);

        // Partner A completes their line first.
        $this->addCompletionDocument($srA->request_id, $this->partnerA->user_id);
        Sanctum::actingAs($this->partnerA, ['*']);
        $this->patchJson("/api/v1/service-completion/{$srA->request_id}/complete", ['payment_received' => 'online'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Order not fully complete yet - no earnings posted.
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PARTNER_EARNING')->count());

        // Partner B completes their line last -> settlement splits per line.
        $this->addCompletionDocument($srB->request_id, $this->partnerB->user_id);
        Sanctum::actingAs($this->partnerB, ['*']);
        $this->patchJson("/api/v1/service-completion/{$srB->request_id}/complete", ['payment_received' => 'online'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);

        // Each partner got exactly one earning for their own line.
        $earningsA = LedgerEntry::where('booking_id', $order->order_id)
            ->where('user_id', $this->partnerA->user_id)->where('type', 'PARTNER_EARNING')->get();
        $earningsB = LedgerEntry::where('booking_id', $order->order_id)
            ->where('user_id', $this->partnerB->user_id)->where('type', 'PARTNER_EARNING')->get();
        $this->assertCount(1, $earningsA);
        $this->assertCount(1, $earningsB);

        // No commission rules are seeded in tests -> the 80% fallback applies
        // to each LINE subtotal, not the whole order.
        $this->assertEqualsWithDelta(1200.00, (float) $earningsA->first()->amount, 0.01);
        $this->assertEqualsWithDelta(2000.00, (float) $earningsB->first()->amount, 0.01);

        // The two earners are the two partners - no third party was credited.
        $creditedUserIds = LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PARTNER_EARNING')->pluck('user_id')->sort()->values()->all();
        $this->assertEquals(
            collect([$this->partnerA->user_id, $this->partnerB->user_id])->sort()->values()->all(),
            $creditedUserIds
        );
    }

    public function test_pas_cash_settlement_reduces_partner_balance_via_getUserBalance(): void
    {
        // Simulate a PAS cash job on partner A: full receipt then cash
        // settlement (posted NEGATIVE) removes the collected cash, leaving
        // only the commission as a liability.
        $order = $this->createOnlineOrder([
            ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
        ]);
        $order->update(['payment_method' => 'pas']);

        app(\App\Services\LedgerService::class)->recordPASReceipt(
            $this->partnerA->user_id,
            $order->order_id,
            1500.00,
            ['subtotal' => 1500.00, 'service_id' => $this->serviceA->service_id],
        );

        $balance = LedgerEntry::getUserBalance($this->partnerA->user_id);
        $this->assertLessThan(0, $balance, 'Cash-settled PAS job must not inflate the partner balance');
    }
}