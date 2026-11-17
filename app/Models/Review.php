<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'ratings';
    protected $primaryKey = 'rating_id';

    protected $fillable = [
        'order_id',
        'user_id',
        'partner_id',
        'rating_value',
        'review_text',
        'partner_response',
        'partner_responded_at'
    ];

    protected $casts = [
        'rating_value' => 'integer',
        'partner_responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id', 'partner_id');
    }

    // Scopes
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating_value', $rating);
    }

    public function scopeWithResponse($query)
    {
        return $query->whereNotNull('partner_response');
    }

    public function scopeWithoutResponse($query)
    {
        return $query->whereNull('partner_response');
    }

    // Helper methods
    public function getStarsAttribute()
    {
        return str_repeat('⭐', $this->rating_value);
    }

    public function hasPartnerResponse()
    {
        return !is_null($this->partner_response);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'rating_id';
    }
}
