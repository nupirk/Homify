<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $table = 'promotions';
    protected $primaryKey = 'promotion_id';

    protected $fillable = [
        'promo_code',
        'title',
        'description',
        'discount_percent',
        'discount_amount',
        'min_order_value',
        'max_discount',
        'usage_limit',
        'usage_count',
        'valid_from',
        'valid_until',
        'is_active',
        'promo_type',
        'service_id',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The service this code is restricted to; null means any service.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }
}
