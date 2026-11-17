<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Earning extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'booking_id', 'amount', 'commission_deducted', 'net_paid', 'paid_at'];
    protected $casts    = ['paid_at' => 'datetime'];

    public function partner() { return $this->belongsTo(User::class, 'partner_id'); }
    public function booking() { return $this->belongsTo(Booking::class); }
}
