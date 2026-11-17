<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $primaryKey = 'device_id';

    protected $fillable = [
        'user_id',
        'device_token',
        'platform',
        'device_model',
        'os_version',
        'app_version',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class, 'device_id', 'device_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeRecentlyUsed($query, $days = 30)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function isIOS(): bool
    {
        return $this->platform === 'ios';
    }

    public function isAndroid(): bool
    {
        return $this->platform === 'android';
    }

    public function isWeb(): bool
    {
        return $this->platform === 'web';
    }
}
