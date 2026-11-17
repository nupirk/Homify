<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
