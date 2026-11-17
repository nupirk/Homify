<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Notification;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Chat notifications are the most easily abused notification in the app: every
 * message used to fan out an in-app row plus an Expo push, even to someone with
 * the thread open in front of them.
 */
class BookingChatNotificationTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partner;
    private Address $address;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'first_name' => 'Chat',
            'last_name' => 'Customer',
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

        PartnerProfile::unguarded(function () {
            PartnerProfile::create([
                'partner_id' => $this->partner->user_id,
                'status' => 'active',
            ]);
        });

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Chat Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Chat Service',
            'description' => 'Fixture service for chat tests',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        ServicePricing::create([
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1000,
            'estimated_duration_min' => 60,
            'is_active' => true,
        ]);

        // The partner must actually offer the service, otherwise checkout
        // rejects the preferred-partner booking with a 400.
        $this->partner->partnerProfile->services()->attach($this->service->service_id);
    }

    /** Create a PAS order assigned to the fixture partner and return its id. */
    private function makeOrder(): string
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDays(2)->format('Y-m-d') . ' 10:00',
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => 'pas',
        ]);

        $response->assertStatus(201);

        return $response->json('order_id');
    }

    private function sendAsCustomer(string $orderId, string $body): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$orderId}/messages", ['body' => $body])
            ->assertStatus(201);
    }

    private function sendAsPartner(string $orderId, string $body): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $this->postJson("/api/v1/bookings/{$orderId}/messages", ['body' => $body])
            ->assertStatus(201);
    }

    private function notificationsFor(User $user): int
    {
        return Notification::where('user_id', $user->user_id)
            ->where('title', 'like', 'New message · Order #%')
            ->count();
    }

    public function test_a_message_notifies_the_other_side_when_they_are_away(): void
    {
        $orderId = $this->makeOrder();

        $this->sendAsCustomer($orderId, 'Hello, are you coming?');

        $this->assertSame(1, $this->notificationsFor($this->partner));
        // The sender never notifies themselves.
        $this->assertSame(0, $this->notificationsFor($this->customer));
    }

    public function test_no_notification_while_the_reader_has_the_thread_open(): void
    {
        $orderId = $this->makeOrder();

        // Partner opens the thread - the screen polls, which records presence.
        Sanctum::actingAs($this->partner, ['*']);
        $this->getJson("/api/v1/bookings/{$orderId}/messages")->assertStatus(200);

        $this->sendAsCustomer($orderId, 'Message the partner is looking at');

        $this->assertSame(
            0,
            $this->notificationsFor($this->partner),
            'An open thread must not produce a notification for a message already on screen.'
        );
    }

    public function test_mark_read_also_records_presence(): void
    {
        $orderId = $this->makeOrder();

        Sanctum::actingAs($this->partner, ['*']);
        $this->postJson("/api/v1/bookings/{$orderId}/messages/read")->assertStatus(200);

        $this->sendAsCustomer($orderId, 'Another one');

        $this->assertSame(0, $this->notificationsFor($this->partner));
    }

    public function test_a_burst_of_messages_sends_one_notification(): void
    {
        $orderId = $this->makeOrder();

        $this->sendAsCustomer($orderId, 'First');

        // Partner is away (never opened the thread); presence is not involved.
        $this->sendAsCustomer($orderId, 'Second');
        $this->sendAsCustomer($orderId, 'Third');

        $this->assertSame(
            1,
            $this->notificationsFor($this->partner),
            'Rapid messages must collapse into a single notification.'
        );
    }

    public function test_partner_messages_notify_the_customer(): void
    {
        $orderId = $this->makeOrder();

        $this->sendAsPartner($orderId, 'On my way');

        $this->assertSame(1, $this->notificationsFor($this->customer));
    }
}
