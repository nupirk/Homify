<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'user_id',
        'order_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'resolved_at'
    ];

    protected $casts = [
        'priority' => 'integer',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* Relations */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'ticket_id');
    }

    /* Helpers */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getPriorityLabelAttribute(): string
    {
        $priorities = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
        return $priorities[$this->priority] ?? 'Unknown';
    }

    public function getPriorityColorAttribute(): string
    {
        $colors = [1 => 'green', 2 => 'yellow', 3 => 'orange', 4 => 'red'];
        return $colors[$this->priority] ?? 'gray';
    }
}
