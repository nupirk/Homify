<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingMessage extends Model
{
    use HasFactory;

    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'sender_id',
        'client_message_id',
        'body',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    /** Display name for a sender: "First Last", else a neutral fallback. */
    public static function displayName(?User $user): string
    {
        if (!$user) {
            return '';
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->user_type === 'partner' ? 'Partner' : 'Customer');
    }
}
