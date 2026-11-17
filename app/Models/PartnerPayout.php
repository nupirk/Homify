<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerPayout extends Model
{
    use HasFactory;

    protected $primaryKey = 'payout_id';

    protected $fillable = [
        'partner_id',
        'amount',
        'platform_fee',
        'net_amount',
        'status',
        'payment_method',
        'transaction_reference',
        'gateway_payout_id',
        'gateway_fee',
        'failure_reason',
        'processed_at',
        'processed_by',
        'notes',
        'metadata',
        'bank_transfer_details',
        'initiated_at',
        'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'metadata' => 'array',
        'bank_transfer_details' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id', 'user_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by', 'user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helpers
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
