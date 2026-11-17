<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'service_id',
        'partner_id',
        'scheduled_at',
        'duration_minutes',
        'address',
        'latitude',
        'longitude',
        'price_agreed',
        'status',
        'rejected_reason',
        'notes'
    ];

    protected $casts = ['scheduled_at' => 'datetime', 'status' => 'string'];

    /* Relations */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }
    public function review()
    {
        return $this->hasOne(Review::class);
    }
    public function earnings()
    {
        return $this->hasOne(Earning::class);
    }
}
