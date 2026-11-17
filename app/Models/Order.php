<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Review;

class Order extends Model
{
    use HasFactory;

    protected $primaryKey = 'order_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'payment_status',
        'payment_method',
        'commission_deducted',
        'commission_deducted_at',
        'promo_code',
        'subtotal',
        'discount_amount',
        'membership_discount',
        'tax_amount',
        'service_charge',
        'platform_fee',
        'coupon_discount',
        'coupon_code',
        'tip_amount',
        'total_amount',
        'final_payable',
        'payment_expires_at',
        'payment_dismissed_at',
        'admin_notes',
        'transaction_uuid',
        'payment_received',
        'cash_confirmed',
        'cash_confirmed_at',
        'cash_disputed',
        'cash_disputed_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'membership_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'final_payable' => 'decimal:2',
        'payment_expires_at' => 'datetime',
        'payment_dismissed_at' => 'datetime',
        'commission_deducted' => 'boolean',
        'commission_deducted_at' => 'datetime',
        'payment_received' => 'string',
        'cash_confirmed' => 'boolean',
        'cash_confirmed_at' => 'datetime',
        'cash_disputed' => 'boolean',
        'cash_disputed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->order_id)) {
                $model->order_id = (string) Str::uuid();
            }
        });
    }

    /**
     * promo_code is a fixed char(20) column - Postgres blank-pads it.
     * Trim on read so API responses and comparisons see the real code.
     */
    public function getPromoCodeAttribute($value)
    {
        return $value === null ? null : rtrim($value);
    }

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'order_id', 'order_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'order_id', 'order_id');
    }

    public function ratings()
    {
        return $this->hasMany(Review::class, 'order_id', 'order_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'order_id', 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id', 'order_id');
    }

    /* Helpers */
    public function getTotalItemsAttribute(): int
    {
        return $this->orderItems->sum('quantity');
    }

    public function getStatusLabelAttribute(): string
    {
        return \App\Helpers\StatusEnumHelper::getStatusLabel($this->status);
    }

    public function getStatusClassesAttribute(): string
    {
        return \App\Helpers\StatusEnumHelper::getOrderStatusClasses($this->status);
    }
}
