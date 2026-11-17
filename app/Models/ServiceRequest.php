<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'partner_id',
        'user_address_id',
        'status',
        'service_date',
        'start_time',
        'end_time',
        'special_instructions',
        'partner_assigned_at',
        'partner_accepted_at',
        'partner_rejected_at',
        'assignment_expires_at',
        'assignment_timeout_minutes',
        'assignment_method',
        'assignment_notes',
        'rejection_reason',
        'response_notes',
        'reassignment_count',
        'reassignment_history',
        'work_started_at',
        'work_completed_at',
        'completion_notes',
        'final_amount',
        'documents_uploaded',
        'customer_confirmed',
        'customer_confirmed_at',
        'customer_rating',
        'customer_feedback',
        'completed_at'
    ];

    protected $casts = [
        'service_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'partner_assigned_at' => 'datetime',
        'partner_accepted_at' => 'datetime',
        'partner_rejected_at' => 'datetime',
        'assignment_expires_at' => 'datetime',
        'work_started_at' => 'datetime',
        'work_completed_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => 'string',
        'final_amount' => 'decimal:2',
        'documents_uploaded' => 'boolean',
        'customer_confirmed' => 'boolean',
        'reassignment_count' => 'integer',
        'reassignment_history' => 'array',
        'customer_rating' => 'integer'
    ];

    /* Relations */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'item_id');
    }

    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id', 'partner_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'user_address_id', 'address_id');
    }

    public function completionDocuments()
    {
        return $this->hasMany(ServiceCompletionDocument::class, 'service_request_id', 'request_id');
    }

    /* Helpers */
    public function getStatusLabelAttribute(): string
    {
        return \App\Helpers\StatusEnumHelper::getStatusLabel($this->status);
    }

    public function getStatusClassesAttribute(): string
    {
        return \App\Helpers\StatusEnumHelper::getServiceRequestStatusClasses($this->status);
    }

    public function getDurationInMinutesAttribute(): ?int
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->diffInMinutes($this->end_time);
        }
        return null;
    }
}
