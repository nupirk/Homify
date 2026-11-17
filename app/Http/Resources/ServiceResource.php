<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get the base price from the first active pricing tier (default to 'basic' or first available)
        $pricing = $this->whenLoaded('pricing', function () {
            return $this->pricing->where('is_active', true)->first();
        });

        $basePrice = 0;
        $durationMinutes = null;

        if ($pricing && is_object($pricing)) {
            $basePrice = $pricing->base_price;
            $durationMinutes = $pricing->estimated_duration_min;
        }

        return [
            'id'              => $this->service_id,
            'name'            => $this->name,
            'description'     => $this->description,
            'base_price'      => (float) $basePrice,
            'duration_minutes' => $durationMinutes,
            'image_url'       => PublicUrl::storage($this->image_url),
            'is_active'       => (bool) $this->is_active,
            'is_featured'     => (bool) $this->is_featured,
            'is_popular'      => (bool) $this->is_popular,
            'requires_prepay' => (bool) ($this->requires_prepay ?? true),
            'category_id'     => $this->category_id,
            'category'        => $this->whenLoaded('category', function () {
                return [
                    'id'          => $this->category->category_id,
                    'name'        => $this->category->name,
                    'description' => $this->category->description,
                    'image_url'   => PublicUrl::storage($this->category->image_url),
                    'is_active'   => (bool) $this->category->is_active,
                ];
            }),
            'features'        => $this->features ? (is_array($this->features) ? $this->features : json_decode($this->features, true)) : [],
            'terms_conditions' => $this->terms_conditions,
            'pricing'         => $this->whenLoaded('pricing', function () {
                return $this->pricing->map(function ($price) {
                    return [
                        'id' => $price->pricing_id,
                        'tier_name' => $price->tier_name,
                        'base_price' => (float) $price->base_price,
                        'min_price' => $price->min_price ? (float) $price->min_price : null,
                        'max_price' => $price->max_price ? (float) $price->max_price : null,
                        'estimated_duration_min' => $price->estimated_duration_min,
                        'description' => $price->description,
                        'unit' => $price->unit,
                        'is_active' => (bool) $price->is_active,
                    ];
                });
            }),
        ];
    }
}
