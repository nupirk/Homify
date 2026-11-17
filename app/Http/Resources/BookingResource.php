<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'status'        => $this->status,
            'scheduled_at'  => $this->scheduled_at->toDateTimeString(),
            'duration'      => $this->duration_minutes,
            'address'       => $this->address,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'price_agreed'  => (float) $this->price_agreed,
            'rejected_reason' => $this->rejected_reason,

            'customer'      => new UserResource($this->whenLoaded('customer')),
            'partner'       => new UserResource($this->whenLoaded('partner')),
            'service'       => new ServiceResource($this->whenLoaded('service')),
            'review'        => new ReviewResource($this->whenLoaded('review')),
        ];
    }
}
