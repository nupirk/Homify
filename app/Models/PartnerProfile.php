<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;

class PartnerProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'partner_id';

    protected $fillable = [
        'status',
        'rating_avg',
        'total_ratings',
        'bank_account',
        'kyc_verified_at',
        'business_name',
        'business_address',
        'latitude',
        'longitude',
        'bio',
        'experience_years',
        'hourly_rate',
        'live_lat',
        'live_lng',
        'live_updated_at'
    ];

    protected $casts = [
        'rating_avg' => 'decimal:2',
        'total_ratings' => 'integer',
        'bank_account' => 'array',
        'kyc_verified_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'live_lat' => 'float',
        'live_lng' => 'float',
        'live_updated_at' => 'datetime',
        'experience_years' => 'integer',
        'hourly_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'partner_id', 'user_id');
    }

    public function skills()
    {
        return $this->hasMany(PartnerSkill::class, 'partner_id', 'partner_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'partner_skills', 'partner_id', 'service_id')
            ->withPivot('base_price');
    }

    public function availability()
    {
        return $this->hasMany(PartnerAvailability::class, 'partner_id', 'partner_id');
    }

    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'partner_id', 'partner_id');
    }

    public function ratings()
    {
        return $this->hasMany(Review::class, 'partner_id', 'partner_id');
    }

    public function payouts()
    {
        return $this->hasMany(PartnerPayout::class, 'partner_id', 'partner_id');
    }

    public function earnings()
    {
        return $this->hasMany(LedgerEntry::class, 'user_id', 'partner_id')
            ->where('type', 'PARTNER_EARNING');
    }

    public function balance()
    {
        return $this->hasOne(PartnerBalance::class, 'partner_id', 'partner_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('kyc_verified_at');
    }

    // Helpers
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return !is_null($this->kyc_verified_at);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user ? $this->user->full_name : '';
    }
}
