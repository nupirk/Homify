<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default plan code
    |--------------------------------------------------------------------------
    | The plan the app highlights by default. Plans themselves live in the
    | membership_plans table (seeded by migration).
    */
    'plan_code' => env('MEMBERSHIP_PLAN_CODE', 'urban_plus'),

    /*
    |--------------------------------------------------------------------------
    | Pending payment window (minutes)
    |--------------------------------------------------------------------------
    | How long a pending membership purchase stays payable before it counts
    | as abandoned. Mirrors the order payment deadline.
    */
    'pending_minutes' => env('MEMBERSHIP_PENDING_MINUTES', 15),
];
