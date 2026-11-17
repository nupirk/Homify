<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $primaryKey = 'account_id';

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'balance'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'account_id', 'account_id');
    }

    // Helpers
    public function getTypeLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->account_type));
    }

    public function updateBalance()
    {
        $totalDebits = $this->entries()->sum('debit_amount');
        $totalCredits = $this->entries()->sum('credit_amount');

        // For asset and expense accounts: balance = debits - credits
        // For liability, equity, and revenue accounts: balance = credits - debits
        if (in_array($this->account_type, ['asset', 'expense'])) {
            $this->balance = $totalDebits - $totalCredits;
        } else {
            $this->balance = $totalCredits - $totalDebits;
        }

        $this->save();
    }
}
