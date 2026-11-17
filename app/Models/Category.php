<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'service_categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'icon_url',
        'image_url',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'category_id';
    }

    // Relationships
    public function services()
    {
        return $this->hasMany(Service::class, 'category_id', 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'category_id');
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    // Helpers
    public function isParent(): bool
    {
        return $this->children()->count() > 0;
    }

    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    public function getFullPathAttribute(): string
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }
}
