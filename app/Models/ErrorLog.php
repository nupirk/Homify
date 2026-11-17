<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * First-party crash/error report captured from the client apps.
 *
 * Reports are de-duplicated by `fingerprint`: repeat hits bump `occurrences`
 * and `last_seen_at` instead of inserting a new row, so the admin list shows
 * distinct problems rather than raw volume.
 */
class ErrorLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fingerprint',
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'stack',
        'context',
        'screen',
        'platform',
        'app_version',
        'user_id',
        'ip_address',
        'user_agent',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
