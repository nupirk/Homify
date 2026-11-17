<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartnerApplication extends Model
{
    use HasFactory;

    protected $primaryKey = 'application_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'emergency_contact',
        'address',
        'city',
        'state',
        'postal_code',
        'experience_years',
        'previous_work',
        'why_partner',
        'bio',
        'hourly_rate',
        'availability_hours',
        'portfolio_links',
        'references',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'created_partner_id',
        'documents'
    ];

    protected $casts = [
        'previous_work' => 'array',
        'availability_hours' => 'array',
        'portfolio_links' => 'array',
        'references' => 'array',
        'documents' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'partner_application_services', 'application_id', 'service_id');
    }

    public function createdPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_partner_id', 'user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    // Helpers
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'converted' => 'Converted to Partner',
            default => ucfirst($this->status)
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'under_review' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'converted' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, ['pending', 'under_review', 'approved']);
    }

    public function hasDocuments(): bool
    {
        return !empty($this->documents);
    }

    public function getExperienceYearsLabelAttribute(): string
    {
        return match ($this->experience_years) {
            0 => 'No Experience',
            1 => '1 Year',
            default => $this->experience_years . ' Years'
        };
    }
}
