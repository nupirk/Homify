<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PartnerBalance;
use App\Models\PartnerProfile;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * A promo is platform marketing spend, not a pay cut for the partner:
 *
 *  - the partner earns their share of the GROSS service value
 *  - the discount is posted as DISCOUNT_EXPENSE on the platform account
 *  - every cash job reconciles: total_amount + discount = earning + fee + tax
 *  - a fully discounted job leaves the partner owed money, never in debt
 */
class PasPromoFundingTest extends TestCase
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

        // The platform account owns the fee / tax / discount legs.
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
            'first_name' => 'Promo', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Promo', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);

        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Promo Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning',
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

        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function makePromo(int $percent, string $code): Promotion
    {
        return Promotion::create([
            'promo_code' => $code,
            'title' => "{$percent}% off",
            'promo_type' => 'percentage',
            'discount_percent' => $percent,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
            'usage_limit' => 0, // unlimited
        ]);
    }

    private function bookWithPromo(string $code): Order
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => 'pas',
            'promo_code' => $code,
        ]);

        $response->assertStatus(201);

        return Order::findOrFail($response->json('order_id'));
    }

    private function completeWithCash(Order $order): void
    {
        $request = $order->serviceRequests()->first();

        ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
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
        ]));

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")->assertStatus(200);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")->assertStatus(200);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_partner_is_paid_on_the_gross_value_and_the_promo_is_platform_spend(): void
    {
        $this->makePromo(10, 'TENOFF');
        $order = $this->bookWithPromo('TENOFF');
        $this->completeWithCash($order);
        $order->refresh();

        $this->assertEqualsWithDelta(150.00, (float) $order->discount_amount, 0.01);

        $rows = LedgerEntry::where('booking_id', $order->order_id)->get()->keyBy('type');
        $earning = (float) $rows['PARTNER_EARNING']->amount;
        $fee = (float) $rows['PLATFORM_FEE']->amount;
        $tax = (float) $rows['TAX_PAYABLE']->amount;
        $discount = (float) $rows['DISCOUNT_EXPENSE']->amount;
        $cash = abs((float) $rows['PAS_CASH_SETTLEMENT']->amount);

        // The 10% promo does not touch the partner's 80% of the gross line.
        $this->assertEqualsWithDelta(1200.00, $earning, 0.01);
        $this->assertEqualsWithDelta(300.00, $fee, 0.01);
        $this->assertEqualsWithDelta(150.00, $discount, 0.01);

        // The customer paid the discounted, taxed total - not the gross one.
        $this->assertEqualsWithDelta((float) $order->total_amount, $cash, 0.01);
        $this->assertEqualsWithDelta(1525.50, $cash, 0.01);

        // Every cash job reconciles, with the discount visible as its own leg.
        $this->assertEqualsWithDelta($cash + $discount, $earning + $fee + $tax, 0.01);

        // And the promo is booked against the platform, not the partner.
        $this->assertSame(
            (int) config('payment.platform_user_id', 1),
            (int) $rows['DISCOUNT_EXPENSE']->user_id
        );

        // The partner holds the cash and earned 1200 of it, so what they owe
        // the platform is cash - earning (negative available balance).
        $balance = PartnerBalance::where('partner_id', $this->partner->user_id)->first();
        $this->assertEqualsWithDelta($earning - $cash, (float) $balance->available_balance, 0.01);
        $this->assertEqualsWithDelta($earning, (float) $balance->total_earnings, 0.01);
    }

    public function test_fully_discounted_job_leaves_the_partner_owed_money_not_in_debt(): void
    {
        $this->makePromo(100, 'FREEFIX');
        $order = $this->bookWithPromo('FREEFIX');
        $this->assertEqualsWithDelta(0.00, (float) $order->total_amount, 0.01);

        $this->completeWithCash($order);
        $order->refresh();

        $earning = (float) LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PARTNER_EARNING')
            ->value('amount');
        $this->assertEqualsWithDelta(1200.00, $earning, 0.01, 'The partner still earns on the gross value');

        // No cash changed hands...
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->count());

        // ...the whole 1500 is platform spend...
        $this->assertEqualsWithDelta(1500.00, (float) LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'DISCOUNT_EXPENSE')
            ->value('amount'), 0.01);

        // ...and the partner's balance is money WE owe them, payable as a payout.
        $balance = PartnerBalance::where('partner_id', $this->partner->user_id)->first();
        $this->assertEqualsWithDelta(1200.00, (float) $balance->available_balance, 0.01);
        $this->assertGreaterThan(
            0,
            (float) $balance->available_balance,
            'A fully promoted job must not put the partner in debt'
        );

        // The earnings row still explains the split even though no cash moved:
        // nothing collected, and the platform owes the partner their share.
        Sanctum::actingAs($this->partner, ['*']);
        $row = collect($this->getJson('/api/v1/earnings')->json('data.earnings.data'))
            ->firstWhere('booking_id', $order->order_id);

        $this->assertIsArray($row, 'A fully promoted job must appear in the partner earnings list');
        $this->assertSame('pas', $row['payment_mode']);
        $this->assertEqualsWithDelta(0.00, (float) $row['cash_collected'], 0.01);
        $this->assertEqualsWithDelta(-1200.00, (float) $row['platform_cut'], 0.01);
        $this->assertSame('pas_settlement', $row['breakdown']['validation_status']);
        $this->assertEqualsWithDelta(1500.00, (float) $row['breakdown']['base_price'], 0.01);
        $this->assertEqualsWithDelta(300.00, (float) $row['breakdown']['platform_fee'], 0.01);
    }
}
