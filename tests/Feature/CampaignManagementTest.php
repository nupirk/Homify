<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Campaign/notification CMS contract (previously untested):
 *  - an admin builds a template, then a campaign that dispatches to a segment
 *  - dispatch writes one in-app notification per targeted ACTIVE user, even
 *    when the user has no registered push device (no 500, no silent skip)
 *  - quick notifications land in the same inbox
 *  - segments, scheduled sends and validation behave as documented
 *  - everything is admin-only (403 for users, 401 for guests)
 */
class CampaignManagementTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;
    private User $customerA;
    private User $customerB;
    private User $inactiveCustomer;
    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Campaign', 'last_name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'admin', 'is_active' => true,
        ]);

        $this->customerA = $this->makeUser('customer', true);
        $this->customerB = $this->makeUser('customer', true);
        $this->inactiveCustomer = $this->makeUser('customer', false);
        $this->partner = $this->makeUser('partner', true);
    }

    private function makeUser(string $type, bool $active): User
    {
        return User::create([
            'first_name' => 'Seg', 'last_name' => ucfirst($type),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => $type, 'is_active' => $active,
        ]);
    }

    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Journey Broadcast',
            'type' => 'promotional',
            'title_template' => 'Hello {{first_name}}',
            'message_template' => 'Big sale for {{first_name}} {{last_name}}',
        ], $overrides);
    }

    private function createTemplate(array $overrides = []): int
    {
        $res = $this->postJson('/api/v1/admin/notifications/templates', $this->templatePayload($overrides));

        $res->assertStatus(200)->assertJsonPath('success', true);

        return (int) $res->json('data.template_id');
    }

    public function test_admin_creates_template_and_campaign_and_every_active_customer_gets_an_in_app_notification(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $templateId = $this->createTemplate();
        $this->assertDatabaseHas('notification_templates', [
            'template_id' => $templateId,
            'type' => 'promotional',
        ]);

        $res = $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'Monsoon blast',
            'template_id' => $templateId,
            'target_type' => 'all_customers',
        ]);

        $res->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.target_type', 'all_customers');

        $campaignId = (int) $res->json('data.campaign_id');

        // Only the two ACTIVE customers are targeted - the inactive customer,
        // the partner and the admin are outside the segment.
        $this->assertSame(2, (int) $res->json('data.total_recipients'));

        foreach ([$this->customerA, $this->customerB] as $customer) {
            $note = Notification::where('user_id', $customer->user_id)
                ->where('title', 'Hello ' . $customer->first_name)
                ->first();

            $this->assertNotNull(
                $note,
                "Customer {$customer->user_id} must receive the campaign in-app even without a push device"
            );
            $this->assertSame(
                'Big sale for ' . $customer->first_name . ' ' . $customer->last_name,
                $note->message
            );
            $this->assertSame($campaignId, (int) ($note->data['campaign_id'] ?? 0));
            $this->assertFalse((bool) $note->is_read);
        }

        $this->assertSame(0, Notification::where('user_id', $this->inactiveCustomer->user_id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->partner->user_id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->admin->user_id)->count());

        // The campaign is queryable back from the admin console.
        $list = $this->getJson('/api/v1/admin/notifications/campaigns');
        $list->assertOk()->assertJsonPath('success', true);
        $listed = collect($list->json('data.data'));
        $campaign = $listed->firstWhere('campaign_id', $campaignId);
        $this->assertNotNull($campaign, 'Created campaign must appear in the campaign list');
        $this->assertSame('completed', $campaign['status']);
        $this->assertSame('Journey Broadcast', $campaign['template']['name']);

        // ...and the customer sees it in their own notification inbox.
        Sanctum::actingAs($this->customerA, ['*']);
        $inbox = $this->getJson('/api/v1/notifications');
        $inbox->assertOk()->assertJsonPath('success', true);
        $titles = collect($inbox->json('data'))->pluck('title')->all();
        $this->assertContains('Hello ' . $this->customerA->first_name, $titles);

        $count = $this->getJson('/api/v1/notifications/unread-count');
        $count->assertOk();
        $this->assertSame(1, (int) $count->json('data.unread_count'));
    }

    public function test_targeted_campaign_reaches_only_the_named_users(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $templateId = $this->createTemplate();

        $res = $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'One to one',
            'template_id' => $templateId,
            'target_type' => 'specific_users',
            'target_criteria' => ['user_ids' => [$this->customerB->user_id]],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_recipients', 1);

        $campaignId = (int) $res->json('data.campaign_id');

        $this->assertSame(1, Notification::where('user_id', $this->customerB->user_id)->count());
        $note = Notification::where('user_id', $this->customerB->user_id)->first();
        $this->assertSame($campaignId, (int) ($note->data['campaign_id'] ?? 0));

        // Untargeted users stay untouched.
        $this->assertSame(0, Notification::where('user_id', $this->customerA->user_id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->inactiveCustomer->user_id)->count());
    }

    public function test_scheduled_campaign_is_not_dispatched_before_scheduled_at(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $templateId = $this->createTemplate();

        $res = $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'Tomorrow teaser',
            'template_id' => $templateId,
            'target_type' => 'all_customers',
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $res->assertStatus(200)->assertJsonPath('data.status', 'scheduled');
        $this->assertNull($res->json('data.sent_at'));

        $this->assertSame(0, Notification::count(), 'A scheduled campaign must not fire immediately');
        $this->assertSame(
            'scheduled',
            NotificationCampaign::findOrFail($res->json('data.campaign_id'))->status
        );
    }

    public function test_quick_notification_is_delivered_to_the_inbox_without_device_tokens(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $res = $this->postJson('/api/v1/admin/notifications/send-quick', [
            'title' => 'Rain delay',
            'message' => 'Your partner may arrive 20 minutes late today.',
            'target_type' => 'specific_users',
            'user_ids' => [$this->customerA->user_id, $this->customerB->user_id],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_recipients', 2);

        foreach ([$this->customerA, $this->customerB] as $customer) {
            $note = Notification::where('user_id', $customer->user_id)
                ->where('title', 'Rain delay')
                ->first();

            $this->assertNotNull(
                $note,
                "Quick notification must reach user {$customer->user_id} with no registered device"
            );
            $this->assertStringContainsString('20 minutes late', $note->message);
            $this->assertSame('admin_broadcast', $note->data['type'] ?? null);
        }

        $this->assertSame(0, Notification::where('user_id', $this->inactiveCustomer->user_id)->count());
    }

    public function test_template_and_campaign_validation_rejects_bad_payloads(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/notifications/templates', [
            'name' => 'No title',
            'type' => 'promotional',
            'message_template' => 'Body without a title',
        ])->assertStatus(422)->assertJsonValidationErrors(['title_template']);

        $this->postJson('/api/v1/admin/notifications/templates', [
            'name' => 'No body',
            'type' => 'promotional',
            'title_template' => 'Title only',
        ])->assertStatus(422)->assertJsonValidationErrors(['message_template']);

        $this->postJson('/api/v1/admin/notifications/templates', [
            'type' => 'promotional',
            'title_template' => 'T',
            'message_template' => 'M',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $templateId = $this->createTemplate();

        $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'Bad segment',
            'template_id' => $templateId,
            'target_type' => 'everyone_i_like',
        ])->assertStatus(422)->assertJsonValidationErrors(['target_type']);

        $this->postJson('/api/v1/admin/notifications/campaigns', [
            'name' => 'Ghost template',
            'template_id' => 424242,
            'target_type' => 'all_customers',
        ])->assertStatus(422)->assertJsonValidationErrors(['template_id']);

        $this->postJson('/api/v1/admin/notifications/campaigns', [
            'template_id' => $templateId,
            'target_type' => 'all_customers',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->postJson('/api/v1/admin/notifications/send-quick', [
            'message' => 'No title',
            'target_type' => 'all_customers',
        ])->assertStatus(422)->assertJsonValidationErrors(['title']);

        $this->postJson('/api/v1/admin/notifications/send-quick', [
            'title' => 'Missing recipients',
            'message' => 'Nobody named',
            'target_type' => 'specific_users',
        ])->assertStatus(422)->assertJsonValidationErrors(['user_ids']);

        // Nothing was dispatched as a side effect of the rejected payloads.
        $this->assertSame(1, NotificationTemplate::count());
        $this->assertSame(0, NotificationCampaign::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_campaign_management_requires_an_active_admin(): void
    {
        $payload = ['name' => 'Nope', 'template_id' => 1, 'target_type' => 'all_customers'];

        Sanctum::actingAs($this->customerA, ['*']);
        $this->postJson('/api/v1/admin/notifications/campaigns', $payload)->assertStatus(403);
        $this->getJson('/api/v1/admin/notifications/campaigns')->assertStatus(403);
        $this->postJson('/api/v1/admin/notifications/templates', $this->templatePayload())
            ->assertStatus(403);
        $this->postJson('/api/v1/admin/notifications/send-quick', [
            'title' => 'T', 'message' => 'M', 'target_type' => 'all_customers',
        ])->assertStatus(403);

        app('auth')->forgetGuards();
        $this->postJson('/api/v1/admin/notifications/campaigns', $payload)->assertStatus(401);
        $this->getJson('/api/v1/admin/notifications/campaigns')->assertStatus(401);

        $this->assertSame(0, NotificationTemplate::count());
        $this->assertSame(0, NotificationCampaign::count());
    }

    public function test_target_count_endpoint_estimates_the_segment_size(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/notifications/target-counts', [
            'target_type' => 'all_customers',
        ])->assertOk()->assertJsonPath('data.estimated_recipients', 2);

        $this->postJson('/api/v1/admin/notifications/target-counts', [
            'target_type' => 'all_partners',
        ])->assertOk()->assertJsonPath('data.estimated_recipients', 1);

        $this->postJson('/api/v1/admin/notifications/target-counts', [
            'target_type' => 'specific_users',
            'criteria' => ['user_ids' => [$this->customerA->user_id]],
        ])->assertOk()->assertJsonPath('data.estimated_recipients', 1);
    }
}
