<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The mobile apps post crashes to POST /api/v1/errors, which de-duplicates by
 * fingerprint. Admins review them in the error reporting portal; this covers
 * the list, the filters and the detail page, plus the sidebar entry that links
 * to it.
 */
class AdminErrorReportsPortalTest extends TestCase
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
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();
    }

    private function makeReport(array $overrides = []): ErrorLog
    {
        return ErrorLog::create(array_merge([
            'fingerprint' => hash('sha256', uniqid('', true)),
            'level' => 'fatal',
            'message' => 'TypeError: cannot read property of undefined',
            'exception_class' => 'TypeError',
            'file' => 'app/cart.tsx',
            'line' => 42,
            'stack' => "TypeError: boom\n    at Cart",
            'context' => ['order' => 7],
            'screen' => 'Cart',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'occurrences' => 3,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    public function test_the_portal_lists_reports_and_is_linked_from_the_sidebar(): void
    {
        $this->makeReport();

        $this->actingAs($this->admin)
            ->get(route('admin.error-reports.index'))
            ->assertOk()
            ->assertSee('Error Reports')
            ->assertSee('TypeError: cannot read property of undefined')
            ->assertSee('android')
            // The sidebar entry resolves to this page.
            ->assertSee(route('admin.error-reports.index'), false);
    }

    public function test_the_portal_shows_an_empty_state(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.error-reports.index'))
            ->assertOk()
            ->assertSee('No error reports');
    }

    public function test_the_portal_filters_by_level(): void
    {
        $this->makeReport(['level' => 'fatal', 'message' => 'a fatal failure']);
        $this->makeReport(['level' => 'debug', 'message' => 'a debug message']);

        $this->actingAs($this->admin)
            ->get(route('admin.error-reports.index', ['level' => 'debug']))
            ->assertOk()
            ->assertSee('a debug message')
            ->assertDontSee('a fatal failure');
    }

    public function test_the_portal_searches_the_message(): void
    {
        $this->makeReport(['message' => 'payment webview crashed']);
        $this->makeReport(['message' => 'something unrelated']);

        $this->actingAs($this->admin)
            ->get(route('admin.error-reports.index', ['search' => 'webview']))
            ->assertOk()
            ->assertSee('payment webview crashed')
            ->assertDontSee('something unrelated');
    }

    public function test_the_detail_page_renders_the_stack_and_context(): void
    {
        $report = $this->makeReport();

        $this->actingAs($this->admin)
            ->get(route('admin.error-reports.show', $report->id))
            ->assertOk()
            ->assertSee('app/cart.tsx:42')
            ->assertSee('at Cart')
            // Context is rendered HTML-escaped on purpose (it comes from the client).
            ->assertSee('&quot;order&quot;: 7', false);
    }

    public function test_the_portal_requires_an_admin(): void
    {
        $this->get(route('admin.error-reports.index'))->assertRedirect(route('admin.login'));
    }
}
