<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';
    protected $primaryKey = 'banner_id';

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'placement',
        'display_style',
        'link_type',
        'link_value',
        'valid_from',
        'valid_until',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'banner_id';
    }
}
