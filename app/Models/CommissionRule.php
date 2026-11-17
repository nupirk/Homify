<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    protected $table = 'commission_rules';
    protected $primaryKey = 'rule_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'service_id',
        'city',
        'slab_start',
        'slab_end',
        'partner_pct',
        'platform_pct',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'slab_start' => 'decimal:2',
        'slab_end' => 'decimal:2',
        'partner_pct' => 'decimal:2',
        'platform_pct' => 'decimal:2',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function partnerEarnings(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'commission_rule_id', 'rule_id')
            ->where('type', 'PARTNER_EARNING');
    }

    // Helper methods
    public function isValidFor(\DateTime $date): bool
    {
        $validFrom = $this->valid_from;
        $validTo = $this->valid_to;

        if ($date < $validFrom) {
            return false;
        }

        if ($validTo && $date > $validTo) {
            return false;
        }

        return true;
    }

    public function appliesTo(float $amount): bool
    {
        if ($amount < $this->slab_start) {
            return false;
        }

        if ($this->slab_end && $amount > $this->slab_end) {
            return false;
        }

        return true;
    }

    public function calculateShares(float $grossAmount): array
    {
        $partnerShare = $grossAmount * ($this->partner_pct / 100);
        $platformShare = $grossAmount * ($this->platform_pct / 100);

        return [
            'partner_share' => round($partnerShare, 2),
            'platform_share' => round($platformShare, 2),
            'gross_amount' => $grossAmount,
        ];
    }

    // Static methods for finding applicable rules
    public static function findApplicableRule(int $serviceId, ?string $city, float $amount, \DateTime $date): ?self
    {
        return self::where('service_id', $serviceId)
            ->where(function ($query) use ($city) {
                $query->whereNull('city')->orWhere('city', $city);
            })
            ->where('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->where('slab_start', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->whereNull('slab_end')->orWhere('slab_end', '>=', $amount);
            })
            ->orderBy('city') // Prioritize city-specific rules over default
            ->orderBy('slab_start', 'desc') // Higher slabs first
            ->first();
    }
}
