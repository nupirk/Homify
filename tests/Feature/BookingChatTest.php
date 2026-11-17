<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\BookingMessage;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * In-booking chat contract:
 *  - only the order owner and the assigned partner can read/write the thread
 *  - everyone else gets 404 (no existence leak)
 *  - after-cursor polling, read receipts, recipient notification, rate limits
 */
class BookingChatTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $outsider;
    private User $partner;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'System',
            'email' => 'platform@urban.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->customer = User::create([
            'first_name' => 'Chat',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->outsider = User::create([
            'first_name' => 'Random',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Chat',
            'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);

        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Chat Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Plumbing', 'is_active' => true]);
        $service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Plumbing Repair',
            'description' => 'Plumbing repair service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);
        ServicePricing::create([
            'category_id' => $category->category_id,
            'service_id' => $service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'is_active' => true,
        ]);
        $this->partner->partnerProfile->services()->attach($service->service_id);

        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $address->address_id,
            'payment_method' => 'pas',
            'preferred_partner_id' => $this->partner->user_id,
        ]);
        $response->assertStatus(201);
        $this->order = Order::findOrFail($response->json('order_id'));
    }

    private function send(User $as, string $body)
    {
        Sanctum::actingAs($as, ['*']);
        return $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages', [
            'body' => $body,
        ]);
    }

    public function test_both_participants_can_exchange_messages(): void
    {
        $this->send($this->customer, 'Hello, on my way?')->assertStatus(201);
        $this->send($this->partner, 'Leaving in 10 minutes')->assertStatus(201);

        Sanctum::actingAs($this->customer, ['*']);
        $data = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $data['messages']);
        $this->assertTrue($data['messages'][0]['is_mine']);
        $this->assertFalse($data['messages'][1]['is_mine']);
        $this->assertSame('Leaving in 10 minutes', $data['messages'][1]['body']);
        $this->assertSame(1, $data['unread']); // partner's reply not yet read
    }

    public function test_outsider_gets_404_everywhere(): void
    {
        Sanctum::actingAs($this->outsider, ['*']);

        $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertStatus(404);
        $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages', [
            'body' => 'let me in',
        ])->assertStatus(404);
        $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages/read')
            ->assertStatus(404);
    }

    public function test_unauthenticated_gets_401(): void
    {
        // setUp authenticated this instance for booking creation - drop it.
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertStatus(401);
    }

    public function test_empty_and_oversized_bodies_are_rejected(): void
    {
        $this->send($this->customer, '   ')->assertStatus(422);
        $this->send($this->customer, str_repeat('a', 1001))->assertStatus(422);
        $this->assertSame(0, BookingMessage::count());
    }

    public function test_after_cursor_returns_only_new_messages(): void
    {
        $this->send($this->customer, 'first')->assertStatus(201);
        $second = $this->send($this->partner, 'second')->assertStatus(201);
        $this->assertNotNull($second->json('data.id'));

        Sanctum::actingAs($this->customer, ['*']);
        $data = $this->getJson(
            '/api/v1/bookings/' . $this->order->order_id . '/messages?after=' . $second->json('data.id')
        )->assertOk()->json('data');

        $this->assertCount(0, $data['messages']);
    }

    public function test_read_receipt_marks_other_side_messages_and_clears_unread(): void
    {
        $this->send($this->customer, 'ping')->assertStatus(201);
        $this->send($this->partner, 'pong')->assertStatus(201);

        // Customer reads the partner's message.
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages/read')
            ->assertOk()
            ->assertJsonPath('data.marked', 1);

        $data = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertOk()
            ->json('data');
        $this->assertSame(0, $data['unread']);

        // Partner still sees the customer's message as unread.
        Sanctum::actingAs($this->partner, ['*']);
        $partnerView = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $partnerView['unread']);
    }

    public function test_sending_notifies_the_other_participant(): void
    {
        $this->send($this->customer, 'Where are you?')->assertStatus(201);

        $notification = Notification::where('user_id', $this->partner->user_id)
            ->where('title', 'LIKE', '%New message%')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Where are you?', $notification->message);

        $this->send($this->partner, 'Almost there')->assertStatus(201);
        $customerNote = Notification::where('user_id', $this->customer->user_id)
            ->where('title', 'LIKE', '%New message%')
            ->first();
        $this->assertNotNull($customerNote);
    }

    public function test_repeated_client_message_id_is_idempotent(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $payload = ['body' => 'Retry me', 'client_message_id' => 'abc-123'];

        $first = $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages', $payload)
            ->assertStatus(201);
        $second = $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages', $payload)
            ->assertStatus(200);

        // Same row returned, only one message stored.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, BookingMessage::where('order_id', $this->order->order_id)->count());
    }

    public function test_messages_carry_sender_names_for_multi_partner_orders(): void
    {
        $this->send($this->partner, 'Partner here')->assertStatus(201);

        Sanctum::actingAs($this->customer, ['*']);
        $messages = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertOk()
            ->json('data.messages');

        $this->assertSame('Chat Partner', $messages[0]['sender_name']);
    }

    public function test_older_pages_can_be_loaded(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->send($this->customer, "msg {$i}")->assertStatus(201);
        }

        Sanctum::actingAs($this->customer, ['*']);

        $all = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/messages')
            ->assertOk()
            ->json('data');
        $this->assertCount(5, $all['messages']);
        $this->assertFalse($all['has_more']);

        // Ask for everything before the 4th message (id 4): expect 3 older rows.
        $before = $all['messages'][3]['id'];
        $older = $this->getJson(
            '/api/v1/bookings/' . $this->order->order_id . '/messages?before=' . $before
        )->assertOk()->json('data.messages');

        $this->assertCount(3, $older);
        $this->assertSame('msg 1', $older[0]['body']);
        $this->assertSame('msg 3', $older[2]['body']);
    }

    public function test_unread_counts_are_per_order_and_exclude_own_messages(): void
    {
        $this->send($this->partner, 'first')->assertStatus(201);
        $this->send($this->partner, 'second')->assertStatus(201);
        $this->send($this->customer, 'my own')->assertStatus(201);

        // Partner only sees the customer's message as unread (never their own).
        Sanctum::actingAs($this->partner, ['*']);
        $partnerCounts = $this->getJson('/api/v1/bookings/chat/unread')
            ->assertOk()
            ->json('data.counts');
        $this->assertSame(1, $partnerCounts[$this->order->order_id]);

        // Customer sees the two partner messages awaiting a read.
        Sanctum::actingAs($this->customer, ['*']);
        $customerCounts = $this->getJson('/api/v1/bookings/chat/unread')
            ->assertOk()
            ->json('data.counts');
        $this->assertSame(2, $customerCounts[$this->order->order_id]);

        // Reading the thread clears only the reader's badge.
        $this->postJson('/api/v1/bookings/' . $this->order->order_id . '/messages/read')
            ->assertOk();
        $afterRead = $this->getJson('/api/v1/bookings/chat/unread')
            ->assertOk()
            ->json('data.counts');
        $this->assertSame([], $afterRead);

        Sanctum::actingAs($this->partner, ['*']);
        $partnerStill = $this->getJson('/api/v1/bookings/chat/unread')
            ->assertOk()
            ->json('data.counts');
        $this->assertSame(1, $partnerStill[$this->order->order_id]);
    }
}
