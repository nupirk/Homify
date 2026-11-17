<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per attributed referee (unique referee_id).
 * pending  = referee redeemed, waiting on their first completed booking
 * credited = reward promo issued to the referrer
 */
class ReferralReward extends Model
{
    protected $table = 'referral_rewards';
    protected $primaryKey = 'reward_id';

    public $timestamps = false;

    protected $fillable = [
        'referrer_id',
        'referee_id',
        'welcome_promo_code',
        'reward_promo_code',
        'status',
        'created_at',
        'credited_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'credited_at' => 'datetime',
    ];
}
