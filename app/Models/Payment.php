<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'gateway',
        'txn_id',
        'amount',
        'status',
        'metadata',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'booking_id', 'order_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'initiated' && !$this->isExpired();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function markAsPaid(string $txnId, array $gatewayData = []): void
    {
        // txn_id keeps the ORIGINAL session uuid (transaction_uuid) so gateway
        // retries and the mobile redirect can always find this payment row
        // again. The gateway's own transaction code is stored in metadata.
        $this->update([
            'status' => 'paid',
            'metadata' => array_merge($this->metadata ?? [], $gatewayData, [
                'gateway_transaction_id' => $txnId,
                'paid_at' => now(),
            ]),
        ]);
    }

    public function markAsFailed(string $reason, array $gatewayData = []): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], [
                'failure_reason' => $reason,
                'failed_at' => now(),
            ], $gatewayData),
        ]);
    }
}
