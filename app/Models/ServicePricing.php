<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePricing extends Model
{
    use HasFactory;

    protected $table = 'service_pricing';
    protected $primaryKey = 'pricing_id';

    protected $fillable = [
        'service_id',
        'tier_name',
        'base_price',
        'min_price',
        'max_price',
        'unit',
        'estimated_duration_min',
        'description',
        'is_active'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'estimated_duration_min' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function getTierDisplayNameAttribute(): string
    {
        return ucfirst($this->tier_name);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rs ' . $this->base_price . ' per ' . $this->unit;
    }
}
