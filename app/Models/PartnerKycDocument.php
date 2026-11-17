<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PartnerKycDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'document_type',
        'document_name',
        'file_path',
        'file_size',
        'file_type',
        'status',
        'uploaded_at',
        'expires_at',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'is_required'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_required' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Document type constants
    const DOCUMENT_TYPES = [
        'license' => 'Professional License',
        'insurance' => 'Insurance Certificate',
        'identity' => 'National ID/Passport',
        'address_proof' => 'Address Proof',
        'bank_statement' => 'Bank Statement',
        'tax_certificate' => 'Tax Certificate'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    // Relationships
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id', 'user_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    // Accessors
    public function getDocumentTypeNameAttribute()
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }

    public function getFileUrlAttribute()
    {
        // Return secure URL that requires authentication
        return route('partner.kyc.document', ['document' => $this->id]);
    }

    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    // Scopes
    public function scopeForPartner($query, $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    // Methods
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function approve($adminId = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'verified_by' => $adminId,
            'verified_at' => now(),
            'rejection_reason' => null
        ]);
    }

    public function reject($reason, $adminId = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_by' => $adminId,
            'verified_at' => now()
        ]);
    }

    public function generateSecureFilePath($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $hashedName = Str::random(40) . '.' . $extension;
        return "kyc/{$this->partner_id}/{$this->document_type}/{$hashedName}";
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            // Delete the physical file when document record is deleted
            if (Storage::disk('private')->exists($document->file_path)) {
                Storage::disk('private')->delete($document->file_path);
            }
        });
    }
}
