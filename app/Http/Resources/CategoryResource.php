<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => PublicUrl::storage($this->image_url),
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'services_count' => $this->whenCounted('services'),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
