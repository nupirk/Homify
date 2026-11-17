<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First-run admin account
    |--------------------------------------------------------------------------
    | Read by Database\Seeders\AdminSeeder. Kept in config (not env()) so the
    | values are still available after `php artisan config:cache` - a cached
    | config skips loading .env, which would silently fall back to the default
    | email and lock you out of the admin panel.
    |
    | ADMIN_PASSWORD null/empty => the seeder generates a strong password and
    | prints it once. An existing admin never has its password reset.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@homify.com.np'),
        'phone' => env('ADMIN_PHONE', '+977-9800000000'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
