<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerAvailability extends Model
{
    use HasFactory;

    protected $table = 'partner_availability';
    protected $primaryKey = 'availability_id';
    public $timestamps = false;

    protected $fillable = [
        'partner_id',
        'service_date',
        'start_time',
        'end_time',
        'slot_length_min',
        'booked'
    ];

    protected $casts = [
        'service_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'slot_length_min' => 'integer',
        'booked' => 'boolean',
    ];

    // Relationships
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id', 'partner_id');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('booked', false);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('service_date', $date);
    }

    // Helpers
    public function getDurationInMinutesAttribute(): int
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->diffInMinutes($this->end_time);
        }
        return $this->slot_length_min;
    }

    public function getTimeSlotAttribute(): string
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }
}
