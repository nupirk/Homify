<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'user_id',
        'session_id',
        'platform',
        'app_version',
        'props',
        'created_at',
    ];

    protected $casts = [
        'props' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
