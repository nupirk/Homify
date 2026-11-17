<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'log_id';

    protected $fillable = [
        'campaign_id',
        'user_id',
        'device_id',
        'notification_id',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'platform',
        'device_token'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function campaign()
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id', 'campaign_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function device()
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered($query)
    {
        return $query->whereNotNull('delivered_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    // Helper methods
    public function isSuccessful(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function getDeliveryTime(): ?int
    {
        if ($this->sent_at && $this->delivered_at) {
            return $this->delivered_at->diffInSeconds($this->sent_at);
        }
        return null;
    }
}
