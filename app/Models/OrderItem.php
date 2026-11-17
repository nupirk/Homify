<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'service_id',
        'quantity',
        'unit_price',
        'total_price',
        'service_details'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'service_details' => 'array',
    ];

    /* Relations */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'order_item_id', 'item_id');
    }
}
