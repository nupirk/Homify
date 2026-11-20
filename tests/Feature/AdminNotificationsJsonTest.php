<?php

namespace Tests\Feature;

use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The admin panel is session-authenticated. Its notification pages used to
 * fetch /api/v1/admin/notifications/*, which sits behind auth:sanctum and
 * answered "Unauthenticated." for a logged-in super admin, so the campaign
 * page could never load. These routes must answer a plain admin session.
 */
class AdminNotificationsJsonTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@homify.local',
            'phone' => '9800000000',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();
    }

    private function makeCampaign(): NotificationCampaign
    {
        $template = NotificationTemplate::unguarded(fn () => NotificationTemplate::create([
            'name' => 'Campaign test template',
            'type' => 'promotional',
            'title_template' => 'Hello {{name}}',
            'message_template' => 'Body text',
            'is_active' => true,
            'created_by' => $this->admin->user_id,
        ]));

        return NotificationCampaign::unguarded(fn () => NotificationCampaign::create([
            'name' => 'Campaign test',
            'template_id' => $template->template_id,
            'target_type' => 'all_users',
            'status' => 'draft',
            'total_recipients' => 3,
            'successful_sends' => 2,
            'failed_sends' => 1,
            'created_by' => $this->admin->user_id,
        ]));
    }

    public function test_admin_session_can_load_a_campaign(): void
    {
        $campaign = $this->makeCampaign();

        $this->actingAs($this->admin)
            ->getJson('/admin/notifications/data/campaigns/' . $campaign->campaign_id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Campaign test')
            ->assertJsonPath('data.total_recipients', 3);
    }

    public function test_admin_session_can_load_campaign_logs(): void
    {
        $campaign = $this->makeCampaign();

        $customer = User::create([
            'first_name' => 'Log',
            'last_name' => 'Recipient',
            'email' => 'recipient@homify.local',
            'phone' => '9812345678',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        NotificationLog::unguarded(fn () => NotificationLog::create([
            'campaign_id' => $campaign->campaign_id,
            'user_id' => $customer->user_id,
            'status' => 'sent',
            'platform' => 'android',
            'sent_at' => now(),
        ]));

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/notifications/data/campaigns/' . $campaign->campaign_id . '/logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $this->assertSame('recipient@homify.local', $response->json('data.0.recipient_email'));
        $this->assertSame('android', $response->json('data.0.platform'));
        $this->assertSame('sent', $response->json('data.0.status'));
    }

    public function test_admin_session_can_load_the_notification_dashboard_lists(): void
    {
        $this->makeCampaign();

        $this->actingAs($this->admin)
            ->getJson('/admin/notifications/data/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($this->admin)
            ->getJson('/admin/notifications/data/campaigns')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($this->admin)
            ->getJson('/admin/notifications/data/templates')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_session_can_update_a_template(): void
    {
        $campaign = $this->makeCampaign();
        $template = NotificationTemplate::findOrFail($campaign->template_id);

        $this->actingAs($this->admin)
            ->putJson('/admin/notifications/data/templates/' . $template->template_id, [
                'name' => 'Campaign test template',
                'type' => 'promotional',
                'title_template' => 'Updated {{name}}',
                'message_template' => 'Updated body',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Updated {{name}}', $template->fresh()->title_template);
    }

    public function test_a_signed_out_or_non_admin_visitor_gets_no_json(): void
    {
        $campaign = $this->makeCampaign();

        $this->getJson('/admin/notifications/data/campaigns/' . $campaign->campaign_id)
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $customer = User::create([
            'first_name' => 'Not',
            'last_name' => 'Admin',
            'email' => 'customer@homify.local',
            'phone' => '9899999999',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->getJson('/admin/notifications/data/campaigns/' . $campaign->campaign_id)
            ->assertStatus(403);
    }
}
