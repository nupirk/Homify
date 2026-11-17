<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $primaryKey = 'template_id';

    protected $fillable = [
        'name',
        'type',
        'title_template',
        'message_template',
        'variables',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'user_id');
    }

    public function campaigns()
    {
        return $this->hasMany(NotificationCampaign::class, 'template_id', 'template_id');
    }

    // Helper methods
    public function renderTitle(array $variables = []): string
    {
        return $this->renderTemplate($this->title_template, $variables);
    }

    public function renderMessage(array $variables = []): string
    {
        return $this->renderTemplate($this->message_template, $variables);
    }

    private function renderTemplate(string $template, array $variables): string
    {
        $rendered = $template;
        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
        }
        return $rendered;
    }

    public function getRequiredVariables(): array
    {
        return $this->variables ?? [];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
