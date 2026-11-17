<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $primaryKey = 'transaction_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transaction_id',
        'order_id',
        'user_id',
        'payment_method_id',
        'transaction_type',
        'status',
        'amount',
        'currency',
        'provider_transaction_id',
        'provider_response',
        'failure_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transaction_id)) {
                $model->transaction_id = (string) Str::uuid();
            }
        });
    }

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'payment_method_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id', 'transaction_id');
    }

    /* Helpers */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getTypeLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->transaction_type));
    }
}
