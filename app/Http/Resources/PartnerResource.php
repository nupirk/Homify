<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->partner_id,
            'status'          => $this->status,
            'bio'             => $this->bio,
            'experience_years' => $this->experience_years,
            'hourly_rate'     => (float) $this->hourly_rate,
            'rating_avg'      => (float) $this->rating_avg,
            'total_ratings'   => $this->total_ratings,
            'user'            => new UserResource($this->whenLoaded('user')),
        ];
    }
}
