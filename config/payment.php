<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'khalti'),

    'payment_timeout_minutes' => env('PAYMENT_TIMEOUT_MINUTES', 15),

    /**
     * The system account that owns platform-side ledger entries
     * (ESCROW_CREDIT, PLATFORM_FEE, TAX_PAYABLE, ...).
     *
     * Must reference a REAL user row - the ledger FK requires it. In dev
     * databases the platform/admin account historically had id 1; on a fresh
     * install the seeder creates it explicitly. Configure the id that exists
     * in the environment you deploy to.
     */
    'platform_user_id' => env('PLATFORM_USER_ID', 1),

    'gateways' => [
        'khalti' => [
            'base_url' => env('KHALTI_BASE_URL', 'https://a.khalti.com/api/v2'),
            'public_key' => env('KHALTI_PUBLIC_KEY'),
            'secret_key' => env('KHALTI_SECRET_KEY'),
            'webhook_key' => env('KHALTI_WEBHOOK_KEY'),
            'enabled' => env('KHALTI_ENABLED', true),
        ],

        'esewa' => [
            'base_url' => env('ESEWA_BASE_URL', 'https://uat.esewa.com.np'),
            'form_url' => env('ESEWA_FORM_URL'), // Payment form host (UAT: https://rc-epay.com.np); falls back to base_url
            'merchant_code' => env('ESEWA_MERCHANT_CODE'),
            'product_code' => env('ESEWA_PRODUCT_CODE', 'EPAYTEST'),
            'secret_key' => env('ESEWA_SECRET_KEY'),
            'enabled' => env('ESEWA_ENABLED', true),
        ],

        'stripe' => [
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'enabled' => env('STRIPE_ENABLED', false),
        ],
    ],

    // Pay-after-service cash jobs are settled by the customer confirming they
    // handed cash over. Nudge them after this many hours and escalate to
    // support (never auto-confirm) after the longer window.
    'cash_reminder_hours' => env('CASH_REMINDER_HOURS', 24),
    'cash_escalation_hours' => env('CASH_ESCALATION_HOURS', 72),

    'fees' => [
        'service_charge_percentage' => env('SERVICE_CHARGE_PERCENTAGE', 5.0), // 5%
        'platform_fee_fixed' => env('PLATFORM_FEE_FIXED', 25.0), // NPR 25
        'tax_percentage' => env('TAX_PERCENTAGE', 13.0), // 13% VAT
    ],
];
