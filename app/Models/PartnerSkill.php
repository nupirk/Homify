<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerSkill extends Model
{
    use HasFactory;

    protected $table = 'partner_skills';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = ['partner_id', 'service_id'];
    protected $keyType = 'array';

    protected $fillable = [
        'partner_id',
        'service_id',
        'base_price'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
    ];

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the value for a given key.
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    // Relationships
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id', 'partner_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }
}
