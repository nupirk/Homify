<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $table = 'membership_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'price',
        'duration_days',
        'perks',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'perks' => 'array',
        'is_active' => 'boolean',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'plan_id');
    }
}
