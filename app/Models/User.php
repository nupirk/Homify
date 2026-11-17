<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Review;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'user_type',
        'partner_application_status',
        'partner_application_submitted_at',
        'partner_application_reviewed_at',
        'partner_application_rejection_reason',
        'partner_application_reviewed_by',
        'avatar_url',
        'is_active',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'partner_application_submitted_at' => 'datetime',
        'partner_application_reviewed_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* Relations */
    public function addresses()
    {
        return $this->hasMany(Address::class, 'user_id', 'user_id');
    }

    public function partnerProfile()
    {
        return $this->hasOne(PartnerProfile::class, 'partner_id', 'user_id');
    }

    public function kycDocuments()
    {
        return $this->hasMany(PartnerKycDocument::class, 'partner_id', 'user_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'partner_skills', 'partner_id', 'service_id')
            ->withPivot('base_price');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'user_id');
    }

    public function customerBookings()
    {
        return $this->hasMany(Booking::class, 'customer_id', 'user_id');
    }

    public function partnerBookings()
    {
        return $this->hasMany(Booking::class, 'partner_id', 'user_id');
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class, 'user_id', 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    public function ratings()
    {
        return $this->hasMany(Review::class, 'user_id', 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'user_id', 'user_id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'user_id');
    }

    public function partnerPayouts()
    {
        return $this->hasMany(PartnerPayout::class, 'partner_id', 'user_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class, 'user_id', 'user_id');
    }

    public function partnerBalance()
    {
        return $this->hasOne(PartnerBalance::class, 'partner_id', 'user_id');
    }

    /* Helpers */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    public function isPartner(): bool
    {
        return $this->user_type === 'partner';
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function getDefaultAddress()
    {
        return $this->addresses()->where('is_default', true)->first();
    }

    // Legacy compatibility - will be removed after full migration
    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    public function getRoleAttribute(): string
    {
        return $this->user_type;
    }
}
