<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Controllers in this app catch their own exceptions and return a 500 without
 * logging anything, so failures left no trace anywhere. RecordServerErrors
 * writes every 5xx to the Laravel log and mirrors it into `error_logs`, where
 * the admin portal lists it next to the app crash reports.
 */
class ServerErrorLoggingTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    public function test_a_swallowed_500_is_logged_and_listed_in_the_portal(): void
    {
        Route::middleware('web')->get('/__test/swallowed-500', fn () => response('boom', 500));

        Log::spy();

        $this->get('/__test/swallowed-500')->assertStatus(500);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message, $context = []) => $message === 'Server error'
                && ($context['status'] ?? null) === 500
                && ($context['method'] ?? null) === 'GET'
                && ($context['path'] ?? null) === '/__test/swallowed-500')
            ->atLeast()->once();

        $entry = ErrorLog::where('platform', 'server')->first();

        $this->assertNotNull($entry, 'A server error should be mirrored into error_logs.');
        $this->assertSame('error', $entry->level);
        $this->assertStringContainsString('HTTP 500', $entry->message);
        $this->assertSame('/__test/swallowed-500', $entry->screen);
        $this->assertSame(1, $entry->occurrences);

        // And it is visible in the admin portal.
        $admin = User::unguarded(fn () => User::create([
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

        $this->actingAs($admin)
            ->get(route('admin.error-reports.index'))
            ->assertOk()
            ->assertSee('HTTP 500 on GET /__test/swallowed-500')
            ->assertSee('server');
    }

    public function test_repeated_identical_failures_bump_occurrences_instead_of_flooding(): void
    {
        Route::middleware('web')->get('/__test/repeated-500', fn () => response('nope', 500));

        $this->get('/__test/repeated-500')->assertStatus(500);
        $this->get('/__test/repeated-500')->assertStatus(500);

        $this->assertSame(1, ErrorLog::where('platform', 'server')->count());
        $this->assertSame(2, ErrorLog::where('platform', 'server')->first()->occurrences);
    }

    public function test_successful_requests_are_not_recorded(): void
    {
        Route::middleware('web')->get('/__test/fine', fn () => response('ok', 200));

        $this->get('/__test/fine')->assertOk();

        $this->assertSame(0, ErrorLog::count());
    }
}
