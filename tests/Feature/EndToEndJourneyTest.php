<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * One sequential happy-path through the whole platform, in the order a real
 * business works:
 *
 *   1. referrer gets an invite code, shopper signs up and redeems it
 *   2. admin markets: promo code, banner, template + campaign dispatch
 *   3. shopper browses the catalogue/feeds and validates the promo
 *   4. shopper books with the promo (money math), chats before assignment
 *   5. admin assigns the partner, partner picks up the chat, accepts, starts
 *   6. partner completes the job: ledger, notifications, referral reward
 *   7. shopper confirms the cash handover: earnings + payout balance
 *   8. second booking paid online through the eSewa webhook + auto-dispatch
 *   9. Homify Plus membership purchased through the signed gateway webhook,
 *      then a member-priced booking
 */
class EndToEndJourneyTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;
    private User $referrer;
    private User $partner;
    private Service $service;
    private Category $category;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

        // Platform/system account that owns the ESCROW_CREDIT, PLATFORM_FEE
        // and TAX_PAYABLE ledger rows (hardcoded as user_id 1 in
        // PaymentLedgerService). It doubles as the super admin in this story.
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

        DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->admin = User::findOrFail(1);

        $this->referrer = User::create([
            'first_name' => 'Re', 'last_name' => 'Ferrer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Journey', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);

        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $this->category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

        $this->service = Service::create([
            'category_id' => $this->category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        ServicePricing::create([
            'category_id' => $this->category->category_id,
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function addCompletionDocument(Order $order): void
    {
        $serviceRequest = $order->serviceRequests()->first();
        $filename = 'after-' . $order->order_id . '.jpg';
        ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
            'service_request_id' => $serviceRequest->request_id,
            'partner_id' => $serviceRequest->partner_id,
            'uploaded_by' => $serviceRequest->partner_id,
            'document_type' => 'after_photo',
            'original_filename' => $filename,
            'stored_filename' => $filename,
            'file_path' => "completions/test/{$filename}",
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'file_hash' => md5($filename),
            'access_token' => Str::uuid()->toString(),
        ]));
    }

    /**
     * eSewa status API: always approve, echoing back the amount we were asked
     * to verify so both the booking payment and the membership purchase pass
     * the gateway amount check.
     */
    private function fakeEsewaApproved(): void
    {
        Http::fake(function ($request) {
            if (!str_contains($request->url(), 'api/epay/transaction/status')) {
                return Http::response('{}', 200);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-JOURNEY',
                'total_amount' => $query['total_amount'] ?? '0.00',
            ], 200);
        });
    }

    /** Signed eSewa success URL, mirroring the gateway envelope. */
    private function membershipSuccessUrl(string $txn, string $totalAmount): string
    {
        $data = [
            'transaction_code' => '111111',
            'status' => 'COMPLETE',
            'total_amount' => $totalAmount,
            'transaction_uuid' => $txn,
            'product_code' => (string) config('payment.gateways.esewa.product_code'),
            'signed_field_names' => 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names',
        ];

        $message = "transaction_code={$data['transaction_code']},status={$data['status']},total_amount={$data['total_amount']},transaction_uuid={$data['transaction_uuid']},product_code={$data['product_code']},signed_field_names={$data['signed_field_names']}";
        $data['signature'] = base64_encode(
            hash_hmac('sha256', $message, (string) config('payment.gateways.esewa.secret_key'), true)
        );

        return '/api/v1/webhooks/membership/esewa/success?data='
            . urlencode(base64_encode(json_encode($data)));
    }

    public function test_full_platform_journey_from_referral_signup_to_membership(): void
    {
        // ------------------------------------------------------------------
        // Step 1: referrer invite code + shopper signs up and redeems it
        // ------------------------------------------------------------------
        Sanctum::actingAs($this->referrer, ['*']);
        $invite = $this->getJson('/api/v1/referral');
        $invite->assertOk()->assertJsonPath('success', true);
        $inviteCode = (string) $invite->json('code');
        $this->assertSame(8, strlen($inviteCode));

        app('auth')->forgetGuards();
        $register = $this->postJson('/api/v1/register', [
            'first_name' => 'Journey',
            'last_name' => 'Shopper',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'customer',
        ]);
        $register->assertStatus(201)->assertJsonPath('success', true);

        $shopper = User::findOrFail($register->json('user.id'));
        $this->assertTrue((bool) $shopper->is_active);
        $this->assertSame('customer', $shopper->user_type);

        Sanctum::actingAs($shopper, ['*']);
        $this->postJson('/api/v1/referral/apply', ['code' => $inviteCode])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame((int) $this->referrer->user_id, (int) $shopper->refresh()->referred_by);

        $reward = ReferralReward::where('referee_id', $shopper->user_id)->firstOrFail();
        $this->assertSame('pending', $reward->status);
        $welcomePromo = (string) $reward->welcome_promo_code;

        $this->assertTrue(
            Notification::where('user_id', $shopper->user_id)
                ->where('title', 'Welcome promo unlocked!')
                ->exists(),
            'Redeeming must greet the shopper in-app'
        );

        // The welcome promo is real money: 15% off the next booking.
        $welcomeValidation = $this->postJson('/api/v1/promotions/validate', [
            'code' => $welcomePromo, 'subtotal' => 1000,
        ]);
        $welcomeValidation->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(150.0, (float) $welcomeValidation->json('discount_amount'));

        // ------------------------------------------------------------------
        // Step 2: admin markets - promo code, banner, template + campaign
        // ------------------------------------------------------------------
        Sanctum::actingAs($this->admin, ['*']);

        $promoRes = $this->postJson('/api/v1/admin/promotions', [
            'promo_code' => 'dashain10',
            'title' => 'Dashain dhamaka',
            'discount_percent' => 10,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'is_active' => true,
        ]);
        $promoRes->assertCreated()->assertJsonPath('data.promotion.promo_code', 'DASHAIN10');

        $bannerRes = $this->postJson('/api/v1/admin/banners', [
            'title' => 'Dashain Sale',
            'subtitle' => 'Up to 40% off',
            'placement' => 'home_mid',
            'image_url' => 'https://cdn.example.com/dashain.png',
            'link_type' => 'promo',
            'link_value' => 'DASHAIN10',
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $bannerRes->assertCreated()->assertJsonPath('data.banner.placement', 'home_mid');
        $bannerId = $bannerRes->json('data.banner.banner_id');
        $this->assertNotNull(Banner::find($bannerId));

        $templateRes = $this->postJson('/api/v1/admin/notifications/templates', [
            'name' => 'Journey Broadcast',
            'type' => 'promotional',
            'title_template' => 'Hello {{first_name}}',
            'message_template' => 'Dashain sale is live for {{first_name}} {{last_name}}',
        ]);
        $templateRes->assertStatus(200)->assertJsonPath('success', true);
        $templateId = $templateRes->json('data.template_id');

        $campaignRes = $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'Dashain blast',
            'template_id' => $templateId,
            'target_type' => 'all_customers',
        ]);
        $campaignRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_recipients', 2);
        $campaignId = $campaignRes->json('data.campaign_id');

        foreach ([$shopper, $this->referrer] as $customer) {
            $note = Notification::where('user_id', $customer->user_id)
                ->where('title', 'Hello ' . $customer->first_name)
                ->first();
            $this->assertNotNull($note, 'Campaign must reach every active customer in-app');
            $this->assertSame($campaignId, (int) ($note->data['campaign_id'] ?? 0));
        }

        // ------------------------------------------------------------------
        // Step 3: shopper browses the catalogue and the marketing feeds
        // ------------------------------------------------------------------
        Sanctum::actingAs($shopper, ['*']);

        $categories = $this->getJson('/api/v1/categories');
        $categories->assertOk()->assertJsonPath('success', true);
        $this->assertContains(
            $this->category->category_id,
            array_column($categories->json('data'), 'id')
        );

        $services = $this->getJson('/api/v1/services');
        $services->assertOk();
        $this->assertContains(
            $this->service->service_id,
            array_column($services->json('data'), 'id')
        );

        $banners = $this->getJson('/api/v1/banners');
        $banners->assertOk();
        $this->assertContains(
            $bannerId,
            array_column($banners->json('data.banners'), 'banner_id')
        );

        $feed = $this->getJson('/api/v1/promotions/active');
        $feed->assertOk();
        $this->assertContains(
            'DASHAIN10',
            array_column($feed->json('data.promotions'), 'promo_code')
        );

        $validation = $this->postJson('/api/v1/promotions/validate', [
            'code' => 'DASHAIN10', 'subtotal' => 1500,
        ]);
        $validation->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(150.0, (float) $validation->json('discount_amount'), '10% of 1500');

        // Shopper saves a delivery address through the API.
        $addressRes = $this->postJson('/api/v1/addresses', [
            'address_line_1' => 'Journey Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);
        $addressRes->assertStatus(201);
        $addressId = $addressRes->json('data.id');

        // ------------------------------------------------------------------
        // Step 4: pay-after-service booking with the promo + chat before
        // anyone is assigned
        // ------------------------------------------------------------------
        $bookingRes = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $addressId,
            'payment_method' => 'pas',
            'promo_code' => 'DASHAIN10',
        ]);
        $bookingRes->assertStatus(201);
        $order = Order::findOrFail($bookingRes->json('order_id'));

        // 1500 gross - 150 promo = 1350 taxable, tax 175.50, total 1525.50.
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('pending', $order->serviceRequests()->first()->status);
        $this->assertSame(1500.0, (float) $order->subtotal);
        $this->assertSame(150.0, (float) $order->discount_amount);
        $this->assertSame(175.5, (float) $order->tax_amount);
        $this->assertSame(1525.5, (float) $order->total_amount);
        $this->assertSame('DASHAIN10', trim((string) $order->promo_code));

        $this->postJson("/api/v1/bookings/{$order->order_id}/messages", [
            'body' => 'Please arrive by 10am',
        ])->assertStatus(201);

        // No partner is assigned yet - the partner and a stranger see nothing.
        Sanctum::actingAs($this->partner, ['*']);
        $this->getJson("/api/v1/bookings/{$order->order_id}/messages")->assertStatus(404);
        Sanctum::actingAs($this->referrer, ['*']);
        $this->getJson("/api/v1/bookings/{$order->order_id}/messages")->assertStatus(404);

        // ------------------------------------------------------------------
        // Step 5: admin assigns the partner, both sides talk, partner works
        // ------------------------------------------------------------------
        Sanctum::actingAs($this->admin, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/assign-partner", [
            'partner_id' => $this->partner->user_id,
        ])->assertOk()->assertJsonPath('success', true);

        $serviceRequest = $order->serviceRequests()->first()->fresh();
        $this->assertSame('assigned', $serviceRequest->status);
        $this->assertSame((int) $this->partner->user_id, (int) $serviceRequest->partner_id);
        $this->assertContains($order->refresh()->status, ['assigned', 'partner_assigned']);

        Sanctum::actingAs($this->partner, ['*']);
        $jobs = $this->getJson('/api/v1/bookings/assigned');
        $jobs->assertOk();
        $this->assertContains($order->order_id, collect($jobs->json('data'))->pluck('id')->all());

        $thread = $this->getJson("/api/v1/bookings/{$order->order_id}/messages");
        $thread->assertOk();
        $bodies = collect($thread->json('data.messages'))->pluck('body')->all();
        $this->assertContains('Please arrive by 10am', $bodies);

        $this->postJson("/api/v1/bookings/{$order->order_id}/messages", [
            'body' => 'On my way now',
        ])->assertStatus(201);

        Sanctum::actingAs($shopper, ['*']);
        $shopperThread = $this->getJson("/api/v1/bookings/{$order->order_id}/messages");
        $shopperThread->assertOk();
        $shopperBodies = collect($shopperThread->json('data.messages'))->pluck('body')->all();
        $this->assertContains('On my way now', $shopperBodies);
        $this->assertGreaterThan(0, (int) $shopperThread->json('data.unread'));

        $this->assertTrue(
            Notification::where('user_id', $shopper->user_id)
                ->where('title', 'LIKE', '%New message%')
                ->exists(),
            'The shopper must be nudged about the partner reply'
        );

        $this->postJson("/api/v1/bookings/{$order->order_id}/messages/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 1);
        $this->assertSame(
            0,
            (int) $this->getJson("/api/v1/bookings/{$order->order_id}/messages")->json('data.unread')
        );

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('accepted', $serviceRequest->fresh()->status);
        $this->assertSame('confirmed', $order->refresh()->status);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('working', $serviceRequest->fresh()->status);
        $this->assertSame('in_progress', $order->refresh()->status);

        // ------------------------------------------------------------------
        // Step 6: partner completes the job - ledger, notifications, reward
        // ------------------------------------------------------------------
        $this->addCompletionDocument($order);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash',
        ])->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_received);
        $this->assertFalse((bool) $order->cash_confirmed);

        $ledgerTypes = LedgerEntry::where('booking_id', $order->order_id)
            ->pluck('type')->sort()->values()->all();
        $this->assertSame(
            ['PAS_CASH_SETTLEMENT', 'PAS_COMMISSION', 'PAS_RECEIPT'],
            $ledgerTypes
        );

        $this->assertTrue(
            Notification::where('user_id', $shopper->user_id)
                ->where('title', 'Service Completed')
                ->exists()
        );
        $this->assertTrue(
            Notification::where('user_id', $shopper->user_id)
                ->where('title', 'Confirm your cash payment')
                ->exists()
        );

        // The shopper's FIRST completed booking credits the referrer.
        $reward->refresh();
        $this->assertSame('credited', $reward->status);
        $this->assertNotNull($reward->reward_promo_code);
        $this->assertNotNull($reward->credited_at);

        $this->assertTrue(
            Notification::where('user_id', $this->referrer->user_id)
                ->where('title', 'Referral reward unlocked!')
                ->exists()
        );

        Sanctum::actingAs($this->referrer, ['*']);
        $rewardValidation = $this->postJson('/api/v1/promotions/validate', [
            'code' => $reward->reward_promo_code, 'subtotal' => 1000,
        ]);
        $rewardValidation->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(100.0, (float) $rewardValidation->json('discount_amount'));

        // ------------------------------------------------------------------
        // Step 7: shopper confirms the cash handover, partner reads earnings
        // ------------------------------------------------------------------
        Sanctum::actingAs($shopper, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $this->assertTrue((bool) $order->cash_confirmed);
        $this->assertNotNull($order->cash_confirmed_at);

        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertOk()
            ->assertJsonPath('message', 'Cash payment already confirmed');

        // Confirmation is verification only - the ledger must not grow.
        $this->assertSame(
            3,
            LedgerEntry::where('booking_id', $order->order_id)->count()
        );

        Sanctum::actingAs($this->partner, ['*']);
        $earnings = $this->getJson('/api/v1/earnings');
        $earnings->assertOk()->assertJsonPath('success', true);

        $entryTypes = collect($earnings->json('data.earnings.data'))
            ->where('booking_id', $order->order_id)
            ->pluck('type')->all();
        $this->assertContains('PAS_RECEIPT', $entryTypes);
        $this->assertContains('PAS_CASH_SETTLEMENT', $entryTypes);
        $this->assertContains('PAS_COMMISSION', $entryTypes);

        // No commission rules are seeded: the 80% fallback of the 1500 line
        // gives the partner a net 1200 (receipt 1500 - commission 300).
        $this->assertEqualsWithDelta(
            1200.0,
            (float) $earnings->json('data.stats.total_earnings'),
            0.01
        );

        $balance = $this->getJson('/api/v1/payouts/balance');
        $balance->assertOk();
        $this->assertEqualsWithDelta(1200.0, (float) $balance->json('data.total_earnings'), 0.01);

        // ------------------------------------------------------------------
        // Step 8: second booking, prepaid online through the eSewa webhook
        // ------------------------------------------------------------------
        Sanctum::actingAs($shopper, ['*']);
        $onlineRes = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $addressId,
            'payment_method' => 'online',
            'promo_code' => $welcomePromo,
        ]);
        $onlineRes->assertStatus(201);
        $onlineOrder = Order::findOrFail($onlineRes->json('order_id'));

        // 1500 - 225 (15% welcome promo) = 1275 taxable, tax 165.75.
        $this->assertSame('created', $onlineOrder->status);
        $this->assertSame('pending', $onlineOrder->payment_status);
        $this->assertSame(225.0, (float) $onlineOrder->discount_amount);
        $this->assertSame(165.75, (float) $onlineOrder->tax_amount);
        $this->assertSame(1440.75, (float) $onlineOrder->total_amount);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $onlineOrder->order_id,
            'gateway' => 'esewa',
        ]);
        $initiate->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($initiate->json('data.payment_url'));

        $payment = Payment::where('booking_id', $onlineOrder->order_id)->firstOrFail();
        $this->assertSame('initiated', $payment->status);

        // Gateway says the money landed (webhook -> status API round trip).
        $this->fakeEsewaApproved();

        $webhook = $this->postJson('/api/v1/webhooks/esewa/payment', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $onlineOrder->total_amount,
        ]);
        $webhook->assertOk()->assertJsonPath('success', true);

        $onlineOrder->refresh();
        $this->assertSame('paid', $onlineOrder->payment_status);
        $this->assertContains($onlineOrder->status, ['confirmed', 'partner_assigned']);

        foreach (['ESCROW_CREDIT', 'PLATFORM_FEE', 'TAX_PAYABLE'] as $type) {
            $this->assertSame(
                1,
                LedgerEntry::where('booking_id', $onlineOrder->order_id)
                    ->where('type', $type)->count(),
                "Paid online booking must post a {$type} row"
            );
        }

        // The platform dispatcher hands the paid job to our partner.
        $this->artisan('bookings:auto-assign')->assertSuccessful();
        $onlineRequest = $onlineOrder->serviceRequests()->first()->fresh();
        $this->assertSame('assigned', $onlineRequest->status);
        $this->assertSame((int) $this->partner->user_id, (int) $onlineRequest->partner_id);
        $this->assertSame('partner_assigned', $onlineOrder->refresh()->status);

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$onlineOrder->order_id}/accept")
            ->assertOk()->assertJsonPath('success', true);
        $this->patchJson("/api/v1/bookings/{$onlineOrder->order_id}/start-work")
            ->assertOk()->assertJsonPath('success', true);

        $this->addCompletionDocument($onlineOrder);
        $this->patchJson("/api/v1/bookings/{$onlineOrder->order_id}/complete", [
            'payment_received' => 'online',
        ])->assertOk()->assertJsonPath('success', true);

        $onlineOrder->refresh();
        $this->assertSame('completed', $onlineOrder->status);
        $this->assertSame('online', $onlineOrder->payment_received);

        $earning = LedgerEntry::where('booking_id', $onlineOrder->order_id)
            ->where('type', 'PARTNER_EARNING')
            ->first();
        $this->assertNotNull($earning, 'Prepaid completion must credit the partner once');
        $this->assertSame((int) $this->partner->user_id, (int) $earning->user_id);
        $this->assertEqualsWithDelta(1200.0, (float) $earning->amount, 0.01);

        // ------------------------------------------------------------------
        // Step 9: Homify Plus through the signed gateway webhook, then a
        // member-priced booking
        // ------------------------------------------------------------------
        Sanctum::actingAs($shopper, ['*']);

        $plans = $this->getJson('/api/v1/membership');
        $plans->assertOk();
        $plan = collect($plans->json('plans'))->firstWhere('code', 'urban_plus');
        $this->assertNotNull($plan, 'The seeded urban_plus plan must be purchasable');

        $purchase = $this->postJson('/api/v1/membership/purchase', [
            'plan_id' => $plan['plan_id'],
        ]);
        $purchase->assertOk()->assertJsonPath('success', true);

        $membership = UserMembership::where('user_id', $shopper->user_id)->firstOrFail();
        $this->assertSame('pending', $membership->status);

        app('auth')->forgetGuards();
        $this->get($this->membershipSuccessUrl($membership->txn_id, '299.00'))
            ->assertStatus(302);

        $this->assertSame('active', $membership->refresh()->status);

        Sanctum::actingAs($shopper, ['*']);
        $memberBooking = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $addressId,
            'payment_method' => 'pas',
        ]);
        $memberBooking->assertStatus(201);
        $memberOrder = Order::findOrFail($memberBooking->json('order_id'));

        // 1500 gross - 150 member (10%) = 1350 taxable, tax 175.50.
        $this->assertSame(150.0, (float) $memberOrder->membership_discount);
        $this->assertSame(150.0, (float) $memberOrder->discount_amount);
        $this->assertSame(175.5, (float) $memberOrder->tax_amount);
        $this->assertSame(1525.5, (float) $memberOrder->total_amount);
        $this->assertNull($memberOrder->promo_code);

        $status = $this->getJson('/api/v1/membership');
        $status->assertOk();
        $this->assertSame('active', $status->json('membership.status'));
    }
}
