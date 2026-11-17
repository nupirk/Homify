<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    use HasFactory;

    protected $primaryKey = 'campaign_id';

    protected $fillable = [
        'name',
        'template_id',
        'target_type',
        'target_criteria',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by',
        'total_recipients',
        'successful_sends',
        'failed_sends'
    ];

    protected $casts = [
        'target_criteria' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_recipients' => 'integer',
        'successful_sends' => 'integer',
        'failed_sends' => 'integer',
    ];

    // Relationships
    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id', 'template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class, 'campaign_id', 'campaign_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_at');
    }

    // Helper methods
    public function getSuccessRate(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }
        return ($this->successful_sends / $this->total_recipients) * 100;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at !== null;
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, ['pending', 'scheduled']) &&
            ($this->scheduled_at === null || $this->scheduled_at <= now());
    }
}
