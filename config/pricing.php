<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    | Configure GST and other tax rates for Nepal
    */
    'tax' => [
        'gst_rate' => 0.13, // 13% GST in Nepal
        'apply_on_total' => true,
        'display_name' => 'GST',
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission Configuration
    |--------------------------------------------------------------------------
    | Default commission rates and constraints
    */
    'commission' => [
        'default_platform_rate' => 0.20, // 20% platform commission
        'default_partner_rate' => 0.80,  // 80% partner earning
        'min_commission_rate' => 0.10,   // Minimum 10% platform commission
        'max_commission_rate' => 0.40,   // Maximum 40% platform commission
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Category Commission Rates
    |--------------------------------------------------------------------------
    | Different commission rates based on service categories
    */
    'service_commission_rates' => [
        // High-skill services - lower commission
        'plumbing' => ['platform' => 0.15, 'partner' => 0.85],
        'electrical' => ['platform' => 0.15, 'partner' => 0.85],
        'carpentry' => ['platform' => 0.18, 'partner' => 0.82],

        // Standard services - standard commission
        'cleaning' => ['platform' => 0.20, 'partner' => 0.80],
        'gardening' => ['platform' => 0.20, 'partner' => 0.80],
        'painting' => ['platform' => 0.22, 'partner' => 0.78],

        // High-volume services - higher commission
        'delivery' => ['platform' => 0.25, 'partner' => 0.75],
        'transportation' => ['platform' => 0.25, 'partner' => 0.75],

        // Default fallback
        'default' => ['platform' => 0.20, 'partner' => 0.80],
    ],

    /*
    |--------------------------------------------------------------------------
    | City Commission Multipliers
    |--------------------------------------------------------------------------
    | Adjust commission rates based on city economics
    */
    'city_commission_multipliers' => [
        'kathmandu' => 1.0,
        'pokhara' => 0.95,
        'chitwan' => 0.90,
        'butwal' => 0.90,
        'biratnagar' => 0.95,
        'dharan' => 0.90,
        'default' => 0.85, // Smaller cities get lower commission rates
    ],

    /*
    |--------------------------------------------------------------------------
    | Amount-based Commission Slabs
    |--------------------------------------------------------------------------
    | Different commission rates based on service amount
    */
    'amount_slabs' => [
        ['min' => 0, 'max' => 500, 'platform' => 0.15, 'partner' => 0.85],
        ['min' => 501, 'max' => 1500, 'platform' => 0.18, 'partner' => 0.82],
        ['min' => 1501, 'max' => 5000, 'platform' => 0.20, 'partner' => 0.80],
        ['min' => 5001, 'max' => 15000, 'platform' => 0.22, 'partner' => 0.78],
        ['min' => 15001, 'max' => PHP_INT_MAX, 'platform' => 0.25, 'partner' => 0.75],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    | Standard currency formatting
    */
    'currency' => [
        'symbol' => 'Rs.',
        'code' => 'NPR',
        'decimal_places' => 2,
    ],
];
