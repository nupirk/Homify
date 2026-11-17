<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Public marketing URL
    |--------------------------------------------------------------------------
    | Canonical/OG/sitemap base for the landing site. Kept separate from
    | APP_URL on purpose: APP_URL drives route() generation (gateway
    | callbacks must reach this server), while public_url is what crawlers
    | and social cards should show. When DNS moves, flip APP_PUBLIC_URL.
    |
    */

    'public_url' => env('APP_PUBLIC_URL', 'https://homify.com.np'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    | Needed when the app is served through a tunnel or reverse proxy (ngrok,
    | Cloudflare, nginx). Laravel then honours X-Forwarded-Proto/Host, so
    | url()/route() emit https callback URLs for gateways such as eSewa.
    | Comma-separated IPs/CIDRs, or "*" to trust everything (local tunnels
    | only - never in production, where X-Forwarded-For is spoofable).
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'Asia/Kathmandu',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed per user (or IP when unauthenticated) by the
    | `throttle:api` middleware. The test suite raises this via phpunit.xml so
    | the shared array cache does not trip the limiter across many tests.
    |
    */

    'api_rate_limit' => env('API_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Expose OTP in API responses
    |--------------------------------------------------------------------------
    |
    | Registration returns the freshly generated OTP so local development can
    | test without a live SMS provider. This defaults to OFF and must be turned
    | on explicitly - previously it leaked whenever APP_ENV was anything other
    | than "production", so a staging deploy silently handed out login codes.
    |
    */

    'expose_dev_otp' => (bool) env('EXPOSE_DEV_OTP', false),

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | db:backup writes a compressed pg_dump archive to storage/backups and
    | prunes archives older than the retention window. Override the pg_dump
    | path when the binary is not on PATH (Windows installs).
    |
    */

    'backup_retention_days' => env('BACKUP_RETENTION_DAYS', 14),
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),

];
