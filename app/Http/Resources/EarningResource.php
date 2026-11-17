<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'earning_id'       => $this->earning_id,
            'order_id'         => $this->order_id,
            'gross_amount'     => (float) $this->gross_amount,
            'partner_share'    => (float) $this->partner_share,
            'platform_share'   => (float) $this->platform_share,
            'calculated_at'    => $this->calculated_at?->toDateTimeString(),
            'order'            => $this->whenLoaded('order', function () {
                return [
                    'order_id' => $this->order->order_id,
                    'total_amount' => (float) $this->order->total_amount,
                    'status' => $this->order->status,
                    'items' => $this->order->items
                ];
            }),
        ];
    }
}
