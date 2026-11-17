<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $primaryKey = 'service_id';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image_url',
        'features',
        'terms_conditions',
        'is_active',
        'is_featured',
        'is_popular',
        'requires_prepay',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'requires_prepay' => 'boolean',
        'features' => 'array',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'service_id';
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function pricing()
    {
        return $this->hasMany(ServicePricing::class, 'service_id', 'service_id');
    }

    public function partnerSkills()
    {
        return $this->hasMany(PartnerSkill::class, 'service_id', 'service_id');
    }

    public function partners()
    {
        return $this->belongsToMany(PartnerProfile::class, 'partner_skills', 'service_id', 'partner_id')
            ->withPivot('base_price');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'service_id', 'service_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'service_id', 'service_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope a query to only include featured services.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include popular services.
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope a query to only include featured or popular services.
     */
    public function scopePromoted($query)
    {
        return $query->where(function ($q) {
            $q->where('is_featured', true)->orWhere('is_popular', true);
        });
    }

    // Helpers
    public function getBasePriceAttribute()
    {
        $basicPricing = $this->pricing()->where('tier_name', 'basic')->first();
        return $basicPricing ? $basicPricing->base_price : null;
    }

    public function getPriceRangeAttribute(): string
    {
        $minPrice = $this->pricing()->min('base_price');
        $maxPrice = $this->pricing()->max('base_price');

        if ($minPrice == $maxPrice) {
            return $minPrice;
        }

        return $minPrice . ' - ' . $maxPrice;
    }
}
