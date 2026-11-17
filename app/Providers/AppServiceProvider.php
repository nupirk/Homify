<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SQLite has no CONCAT() or DATE_TRUNC(). Reporting queries written for
        // PostgreSQL use both, so they are registered as plain SQLite functions
        // on every connection rather than rewritten driver by driver. Registered
        // here, not in boot(), because a connection can be opened while other
        // providers are still booting.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            $connection = $event->connection;

            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $connection->getPdo();

            if (! $pdo instanceof PDO) {
                return;
            }

            $pdo->sqliteCreateFunction('concat', static function (...$parts) {
                return implode('', array_map(static fn ($part) => (string) ($part ?? ''), $parts));
            }, -1);

            $pdo->sqliteCreateFunction('date_trunc', static function ($part, $value) {
                if ($value === null) {
                    return null;
                }

                $timestamp = strtotime((string) $value) ?: 0;

                return match (strtolower((string) $part)) {
                    'year' => date('Y-01-01 00:00:00', $timestamp),
                    'month' => date('Y-m-01 00:00:00', $timestamp),
                    'day' => date('Y-m-d 00:00:00', $timestamp),
                    'hour' => date('Y-m-d H:00:00', $timestamp),
                    default => date('Y-m-d H:i:s', $timestamp),
                };
            }, 2);
        });
    }

    public function boot(): void
    {
        // Behind a tunnel/reverse proxy (ngrok, Cloudflare, nginx) the
        // forwarded proto must be trusted, otherwise url()/route() emit
        // http:// gateway callback URLs. Set TRUSTED_PROXIES=* for local
        // tunnels; list exact IPs/CIDRs in production. Runs here (not in
        // bootstrap/app.php) because the container does not have config yet
        // when the middleware callback fires.
        $trustedProxies = config('app.trusted_proxies');

        if (! empty($trustedProxies)) {
            TrustProxies::at(
                $trustedProxies === '*'
                    ? '*'
                    : array_map('trim', explode(',', (string) $trustedProxies))
            );
        }

        // Authorizes the can:super-admin routes; without this ability the
        // can:super-admin middleware denies every request.
        Gate::define('super-admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('api', function (Request $request) {
            // Config-driven so tests (and load tests) can raise the ceiling;
            // without it the shared array cache trips the limit mid-suite.
            $perMinute = (int) config('app.api_rate_limit', 120);

            return Limit::perMinute($perMinute)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });

        RateLimiter::for('partner-apply', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

    }
}
