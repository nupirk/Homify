<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->rating_id,
            'rating' => $this->rating_value,
            'comment' => $this->review_text,
            'partner_response' => $this->partner_response,
            'partner_responded_at' => $this->partner_responded_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),

            // Formatted display
            'stars' => $this->stars,
            'has_partner_response' => $this->hasPartnerResponse(),
            'time_ago' => $this->created_at->diffForHumans(),

            // Relationships
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->user_id,
                    'name' => $this->customer->first_name . ' ' . $this->customer->last_name,
                    'avatar_url' => $this->customer->avatar_url,
                    'initials' => strtoupper(substr($this->customer->first_name, 0, 1) . substr($this->customer->last_name, 0, 1)),
                ];
            }),

            'partner' => $this->whenLoaded('partner', function () {
                return [
                    'id' => $this->partner->partner_id,
                    'business_name' => $this->partner->business_name,
                    'user' => [
                        'id' => $this->partner->user->user_id,
                        'name' => $this->partner->user->first_name . ' ' . $this->partner->user->last_name,
                        'avatar_url' => $this->partner->user->avatar_url,
                    ]
                ];
            }),

            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->order_id,
                    'order_number' => $this->order->order_number,
                    'service_name' => $this->order->orderItems->first()?->service->name ?? 'Service',
                    'completed_at' => $this->order->completed_at?->toDateTimeString(),
                ];
            }),
        ];
    }
}
