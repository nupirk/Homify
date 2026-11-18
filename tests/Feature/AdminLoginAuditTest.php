<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The admin web login was silent: a successful sign-in left no trace, and a
 * brute-force attempt was indistinguishable from normal traffic. Every attempt
 * now reaches the Laravel log (email, IP, user agent) and the audit trail shown
 * on the Login Activity page.
 */
class AdminLoginAuditTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'admin@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();
    }

    public function test_a_successful_login_is_written_to_the_log_and_the_audit_trail(): void
    {
        Log::spy();

        // Deliberately mixed case: the stored email is canonical.
        $this->post('/admin/login', [
            'email' => 'ADMIN@Homify.local',
            'password' => 'secret',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->user_id,
            'action' => 'admin_login',
            'entity_type' => 'admin_auth',
            'entity_id' => 'admin@homify.local',
            'ip_address' => '127.0.0.1',
        ]);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'Admin login succeeded'
                && ($context['email'] ?? null) === 'admin@homify.local'
                && ($context['admin_id'] ?? null) === $this->admin->user_id
                && ($context['ip'] ?? null) === '127.0.0.1'
                && !empty($context['user_agent']))
            ->atLeast()->once();

        // The password must never reach the log.
        Log::shouldNotHaveReceived('info', [
            fn ($message, $context = []) => str_contains(json_encode([$message, $context]), 'secret'),
        ]);
    }

    public function test_a_wrong_password_is_logged_as_a_warning_with_ip_and_reason(): void
    {
        Log::spy();

        $this->post('/admin/login', [
            'email' => 'admin@homify.local',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_login_failed',
            'entity_type' => 'admin_auth',
            'entity_id' => 'admin@homify.local',
            'ip_address' => '127.0.0.1',
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'Admin login failed'
                && ($context['reason'] ?? null) === 'invalid_credentials'
                && ($context['email'] ?? null) === 'admin@homify.local'
                && ($context['ip'] ?? null) === '127.0.0.1')
            ->atLeast()->once();
    }

    public function test_a_non_admin_account_is_rejected_and_recorded(): void
    {
        $customer = User::unguarded(fn () => User::create([
            'user_id' => 2,
            'first_name' => 'Normal',
            'last_name' => 'User',
            'email' => 'customer@homify.local',
            'phone' => '9800000002',
            'password' => bcrypt('secret'),
            'user_type' => 'customer',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();

        $this->post('/admin/login', [
            'email' => 'customer@homify.local',
            'password' => 'secret',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $customer->user_id,
            'action' => 'admin_login_failed',
            'entity_id' => 'customer@homify.local',
        ]);

        $this->assertSame(
            'not_admin',
            AuditLog::where('action', 'admin_login_failed')->latest('log_id')->first()->new_values['reason']
        );
    }

    public function test_logout_is_recorded(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->user_id,
            'action' => 'admin_logout',
            'entity_type' => 'admin_auth',
        ]);
    }

    public function test_the_login_activity_page_lists_attempts_with_ip_and_client(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->user_id,
            'action' => 'admin_login',
            'entity_type' => 'admin_auth',
            'entity_id' => 'admin@homify.local',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AdminBrowser',
        ]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'admin_login_failed',
            'entity_type' => 'admin_auth',
            'entity_id' => 'attacker@example.com',
            'ip_address' => '198.51.100.7',
            'user_agent' => 'curl/8.5.0',
            'new_values' => ['reason' => 'invalid_credentials'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.login-activity.index'))
            ->assertOk()
            ->assertSee('Login Activity')
            ->assertSee('203.0.113.9')
            ->assertSee('198.51.100.7')
            ->assertSee('attacker@example.com')
            ->assertSee('invalid credentials')
            // Linked from the sidebar.
            ->assertSee(route('admin.login-activity.index'), false);
    }

    public function test_the_login_activity_page_filters_failures(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->user_id,
            'action' => 'admin_login',
            'entity_type' => 'admin_auth',
            'entity_id' => 'admin@homify.local',
            'ip_address' => '203.0.113.9',
        ]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'admin_login_failed',
            'entity_type' => 'admin_auth',
            'entity_id' => 'attacker@example.com',
            'ip_address' => '198.51.100.7',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.login-activity.index', ['action' => 'admin_login_failed']))
            ->assertOk()
            ->assertSee('attacker@example.com')
            ->assertDontSee('203.0.113.9');
    }

    public function test_the_login_activity_page_requires_an_admin(): void
    {
        $this->get(route('admin.login-activity.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_dashboard_surfaces_errors_and_login_activity(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Errors Reported (24h)')
            ->assertSee('Admin Logins (24h)')
            ->assertSee('Failed Admin Logins (24h)')
            ->assertSee(route('admin.login-activity.index'), false);
    }
}
