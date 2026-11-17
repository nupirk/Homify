<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'message_text',
        'attachments'
    ];

    protected $casts = [
        'attachments' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id', 'ticket_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    // Helpers
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }
}
