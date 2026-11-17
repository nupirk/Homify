<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order_id,
            'user_id' => $this->user_id,
            'status' => $this->status, // Always use the order status for consistency
            'order_status' => $this->status, // Keep original order status
            'service_request_status' => $this->whenLoaded('serviceRequests', function () {
                // Service request status available separately if needed
                return $this->serviceRequests->first()?->status ?? null;
            }) ?? null,
            'payment_status' => $this->payment_status ?? null,
            'payment_method' => $this->payment_method ?? null,
            'payment_received' => $this->payment_received ?? null,
            'cash_confirmed' => (bool) ($this->cash_confirmed ?? false),
            'cash_confirmed_at' => $this->cash_confirmed_at ?? null,
            'cash_disputed' => (bool) ($this->cash_disputed ?? false),
            'cash_disputed_at' => $this->cash_disputed_at ?? null,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tip_amount' => (float) $this->tip_amount,
            'total_amount' => (float) $this->total_amount,
            'service_price' => $this->whenLoaded('items', function () {
                return (float) $this->items->sum('total_price'); // Service price only
            }) ?? 0,
            'partner_share' => $this->whenLoaded('items', function () {
                $servicePrice = $this->items->sum('total_price');
                // Use 80% as standard commission (20% platform fee)
                $commissionRate = 0.8;
                return (float) ($servicePrice * $commissionRate);
            }) ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Customer Information
            'user' => $this->whenLoaded('user', function () {
                // Check if any service request is still in 'assigned' status (not accepted yet)
                $hasUnacceptedRequest = $this->whenLoaded('serviceRequests', function () {
                    return $this->serviceRequests->contains('status', 'assigned');
                }) ?? false;

                // Hide customer details if job not accepted yet
                if ($hasUnacceptedRequest) {
                    return [
                        'id' => $this->user->user_id,
                        'name' => '***',
                        'first_name' => '***',
                        'last_name' => '***',
                        'phone' => '***',
                        'email' => $this->user->email,
                        'avatar_url' => $this->user->avatar_url,
                    ];
                }

                // Show full customer details for accepted jobs
                return [
                    'id' => $this->user->user_id,
                    'name' => trim($this->user->first_name . ' ' . $this->user->last_name),
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'phone' => $this->user->phone,
                    'email' => $this->user->email,
                    'avatar_url' => $this->user->avatar_url,
                ];
            }),

            // Assigned Partner (customer-facing convenience field)
            'partner' => $this->whenLoaded('serviceRequests', function () {
                $request = $this->serviceRequests->first();
                $partner = $request?->partner;
                if (!$partner) {
                    return null;
                }

                return [
                    'id' => $partner->partner_id,
                    'partner_id' => $partner->partner_id,
                    'status' => $partner->status,
                    'rating_avg' => (float) ($partner->rating_avg ?? 0),
                    'total_ratings' => (int) ($partner->total_ratings ?? 0),
                    'user' => $partner->user ? [
                        'user_id' => $partner->user->user_id,
                        'first_name' => $partner->user->first_name,
                        'last_name' => $partner->user->last_name,
                        'phone' => $partner->user->phone,
                        'email' => $partner->user->email,
                        'avatar_url' => $partner->user->avatar_url,
                    ] : null,
                ];
            }) ?? null,

            // First service (customer-facing convenience field)
            'service' => $this->whenLoaded('items', function () {
                $item = $this->items->first();
                if (!$item || !$item->service) {
                    return null;
                }

                return [
                    'id' => $item->service->service_id,
                    'name' => $item->service->name,
                    'description' => $item->service->description,
                    'category' => $item->service->category,
                ];
            }) ?? null,

            // Service Items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->item_id,
                        'service_id' => $item->service_id,
                        'service' => $item->service ? [
                            'id' => $item->service->service_id,
                            'service_name' => $item->service->name,
                            'description' => $item->service->description,
                            'category' => $item->service->category,
                        ] : null,
                        'service_name' => $item->service->name ?? null,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                        'price' => (float) $item->unit_price, // For frontend compatibility
                    ];
                });
            }),

            // Service Requests with full details
            'service_requests' => $this->whenLoaded('serviceRequests', function () {
                return $this->serviceRequests->map(function ($request) {
                    return [
                        'id' => $request->request_id,
                        'status' => $request->status,
                        'service_date' => $request->service_date,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'special_instructions' => $request->special_instructions,
                        'partner_assigned_at' => $request->partner_assigned_at,
                        'completed_at' => $request->completed_at,

                        // Address Information
                        'address' => $request->address ? [
                            'id' => $request->address->address_id,
                            'address_line_1' => $request->address->address_line_1,
                            'address_line_2' => $request->address->address_line_2,
                            'city' => $request->address->city,
                            'state' => $request->address->state,
                            'postal_code' => $request->address->postal_code,
                            'country' => $request->address->country,
                            'full_address' => $request->address->full_address,
                            'latitude' => $request->address->latitude,
                            'longitude' => $request->address->longitude,
                        ] : null,
                    ];
                });
            }),

            // Convenience fields for frontend
            'scheduled_at' => $this->whenLoaded('serviceRequests', function () {
                return $this->serviceRequests->first()?->service_date ?? null;
            }) ?? null,
            'address' => $this->whenLoaded('serviceRequests', function () {
                return $this->serviceRequests->first()?->address?->full_address ?? 'Address not provided';
            }) ?? 'Address not provided',
            'special_instructions' => $this->whenLoaded('serviceRequests', function () {
                return $this->serviceRequests->first()?->special_instructions ?? null;
            }) ?? null,
        ];
    }
}
