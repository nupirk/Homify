<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_method_id';

    protected $fillable = [
        'user_id',
        'provider',
        'method_type',
        'method_display_name',
        'encrypted_details',
        'is_default'
    ];

    protected $casts = [
        'encrypted_details' => 'encrypted:array', // Auto encrypt/decrypt
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_method_id', 'payment_method_id');
    }

    /* Accessors & Helpers */
    public function getMethodDisplayNameAttribute(): string
    {
        return $this->attributes['method_display_name'] ?? ucfirst(str_replace('_', ' ', $this->method_type));
    }

    public function getDecryptedDetailsAttribute(): array
    {
        return $this->encrypted_details ?? [];
    }

    /**
     * Get formatted display string for the payment method
     */
    public function getDisplayStringAttribute(): string
    {
        $details = $this->decrypted_details;

        switch ($this->method_type) {
            case 'bank_transfer':
                return $details['bank_name'] . ' - ' . substr($details['account_number'], -4);

            case 'esewa':
                return 'eSewa - ' . $details['esewa_number'];

            case 'khalti':
                return 'Khalti - ' . $details['khalti_number'];

            case 'connectips':
                return $details['bank_name'] . ' (ConnectIPS) - ' . substr($details['account_number'], -4);

            default:
                return $this->method_display_name;
        }
    }

    /**
     * Get safe details (masked sensitive information)
     */
    public function getSafeDetailsAttribute(): array
    {
        $details = $this->decrypted_details;

        switch ($this->method_type) {
            case 'bank_transfer':
                return [
                    'bank_name' => $details['bank_name'] ?? '',
                    'account_number' => '**** ' . substr($details['account_number'] ?? '', -4),
                    'account_name' => $details['account_name'] ?? '',
                    'branch' => $details['branch'] ?? '',
                ];

            case 'esewa':
                return [
                    'esewa_number' => substr($details['esewa_number'] ?? '', 0, 3) . '****' . substr($details['esewa_number'] ?? '', -2),
                    'esewa_name' => $details['esewa_name'] ?? '',
                ];

            case 'khalti':
                return [
                    'khalti_number' => substr($details['khalti_number'] ?? '', 0, 3) . '****' . substr($details['khalti_number'] ?? '', -2),
                    'khalti_name' => $details['khalti_name'] ?? '',
                ];

            case 'connectips':
                return [
                    'bank_name' => $details['bank_name'] ?? '',
                    'account_number' => '**** ' . substr($details['account_number'] ?? '', -4),
                    'account_name' => $details['account_name'] ?? '',
                    'bank_code' => $details['bank_code'] ?? '',
                ];

            default:
                return $details;
        }
    }
}
