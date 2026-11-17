<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCompletionDocument extends Model
{
    use HasFactory;

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'service_request_id',
        'partner_id',
        'uploaded_by',
        'document_type',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'file_hash',
        'access_token',
        'expires_at',
        'is_verified',
        'verified_by',
        'verified_at',
        'is_deleted',
        'deleted_by',
        'deleted_at',
        'last_accessed_at',
        'access_count',
        'metadata'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_deleted' => 'boolean',
        'file_size' => 'integer',
        'access_count' => 'integer',
        'metadata' => 'array'
    ];

    protected $hidden = [
        'access_token',
        'file_path'
    ];

    /**
     * Get the service request that owns this document
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id', 'request_id');
    }

    /**
     * Get the partner who uploaded this document
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who verified this document
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who deleted this document
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Scope for non-deleted documents
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for documents by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Get human readable file size
     */
    public function getHumanFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get secure download URL
     */
    public function getDownloadUrlAttribute()
    {
        return route('api.service-completion.download-document', [
            'documentId' => $this->document_id,
            'accessToken' => $this->access_token
        ]);
    }

    /**
     * Check if document type requires verification
     */
    public function requiresVerification(): bool
    {
        return in_array($this->document_type, ['signed_form', 'bill_receipt']);
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified($verifiedBy = null)
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => $verifiedBy,
            'verified_at' => now()
        ]);
    }

    /**
     * Soft delete document
     */
    public function softDelete($deletedBy = null)
    {
        $this->update([
            'is_deleted' => true,
            'deleted_by' => $deletedBy,
            'deleted_at' => now()
        ]);
    }
}
