<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMembership extends Model
{
    protected $table = 'user_memberships';

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'price_paid',
        'gateway',
        'txn_id',
        'starts_at',
        'ends_at',
        'payment_expires_at',
        'metadata',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    /** Entitlement rows whose window still covers now. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('ends_at', '>', now());
    }
}
