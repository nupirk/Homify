<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user promotion usage / targeted assignment (composite PK
 * user_id + promotion_id). Tracks which promos a customer has
 * redeemed so a code can be limited to one use per customer.
 */
class UserPromotion extends Model
{
    protected $table = 'user_promotions';

    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'user_id',
        'promotion_id',
        'used',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
