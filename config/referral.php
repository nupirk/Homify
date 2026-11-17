<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Referral rewards (all issued as single-use promo codes)
    |--------------------------------------------------------------------------
    | Referee gets a welcome promo the moment they redeem an invite code;
    | the referrer gets theirs when the referee completes their first
    | booking. Discounts are platform-funded, off the gross subtotal.
    */

    // Percent off for the person who was invited (their next booking).
    'welcome_percent' => (int) env('REFERRAL_WELCOME_PERCENT', 15),

    // Percent off for the person who shared the code.
    'reward_percent' => (int) env('REFERRAL_REWARD_PERCENT', 10),

    // Minimum order value (NPR) both promos apply to.
    'min_order_value' => (float) env('REFERRAL_MIN_ORDER_VALUE', 500),

    // Days before either promo expires.
    'validity_days' => (int) env('REFERRAL_VALIDITY_DAYS', 30),

];
