<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

/**
 * PAS cash settlement lifecycle: completion posts the receipts, the customer
 * confirms or disputes, and a dispute reverses the partner ledger entries
 * exactly once (confirm-after-dispute and dispute-after-confirm are refused).
 */
class CashSettlementDisputeTest extends TestCase
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
        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

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

    private function createPasOrder(): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
                ['service_id' => $this->serviceB->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'pas',
        ]);
        $response->assertStatus(201);
        return Order::findOrFail($response->json('order_id'));
    }

    private function completeWithCash(Order $order): void
    {
        $srs = $order->serviceRequests()->orderBy('request_id')->get();
        foreach ($srs as $index => $sr) {
            $partner = $index === 0 ? $this->partnerA : $this->partnerB;
            $sr->update(['partner_id' => $partner->user_id, 'status' => 'in_progress', 'partner_assigned_at' => now()]);

            $filename = "cash-{$sr->request_id}.jpg";
            ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
                'service_request_id' => $sr->request_id,
                'partner_id' => $partner->user_id,
                'uploaded_by' => $partner->user_id,
                'document_type' => 'after_photo',
                'original_filename' => $filename,
                'stored_filename' => $filename,
                'file_path' => "completions/test/{$filename}",
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'file_hash' => md5($filename),
                'access_token' => \Illuminate\Support\Str::uuid()->toString(),
            ]));

            Sanctum::actingAs($partner, ['*']);
            $this->patchJson("/api/v1/service-completion/{$sr->request_id}/complete", ['payment_received' => 'cash'])
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('cash', $order->payment_received);
    }

    public function test_confirm_cash_after_dispute_is_refused_and_dispute_reverses_once(): void
    {
        $order = $this->createPasOrder();
        $this->completeWithCash($order);
        $this->assertSame(2, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'PAS_RECEIPT')->count());

        // Customer disputes the cash declaration.
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/dispute-cash", ['reason' => 'I never paid cash'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertTrue($order->cash_disputed);
        $this->assertFalse($order->cash_confirmed);

        // Reversal entries for BOTH partners (one PAS_RECEIPT reversal each).
        $receiptReversals = LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PAS_RECEIPT')
            ->whereRaw("(metadata->>'dispute_reversal') = 'true'")
            ->get();
        $this->assertSame(2, $receiptReversals->count());
        foreach ($receiptReversals as $reversal) {
            $this->assertLessThan(0, (float) $reversal->amount);
        }

        // Confirm-cash after dispute is refused (409).
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(409);

        // A second dispute is a no-op: no duplicate reversals.
        $this->postJson("/api/v1/bookings/{$order->order_id}/dispute-cash", ['reason' => 'again'])
            ->assertStatus(409);
        $this->assertSame(2, LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PAS_RECEIPT')
            ->whereRaw("(metadata->>'dispute_reversal') = 'true'")
            ->count());
    }

    public function test_confirm_cash_confirms_and_dispute_after_confirm_is_refused(): void
    {
        $order = $this->createPasOrder();
        $this->completeWithCash($order);

        // Customer confirms the cash handover.
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertTrue($order->cash_confirmed);
        $this->assertFalse($order->cash_disputed);

        // Dispute after confirmation is refused (409) - the money is settled.
        $this->postJson("/api/v1/bookings/{$order->order_id}/dispute-cash", ['reason' => 'too late'])
            ->assertStatus(409);

        $order->refresh();
        $this->assertTrue($order->cash_confirmed);
        $this->assertFalse($order->cash_disputed);

        // No reversal entries were posted.
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PAS_RECEIPT')
            ->whereRaw("(metadata->>'dispute_reversal') = 'true'")
            ->count());
    }
}