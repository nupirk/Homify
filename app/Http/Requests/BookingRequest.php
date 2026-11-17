<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            // Services array - support multiple services in one booking
            'services'                => 'required|array|min:1|max:10',
            'services.*.service_id'   => 'required|exists:services,service_id',
            'services.*.quantity'     => 'required|integer|min:1|max:10',

            'scheduled_at'            => 'required|date',

            // Require existing saved address
            'address_id'              => 'required|exists:addresses,address_id',

            // Optional preferred partner selection
            'preferred_partner_id'    => 'nullable|exists:partner_profiles,partner_id',
            'partner_selection_reason' => 'nullable|string|in:high_rating,low_price,availability,location,recommendation',

            'notes'                   => 'nullable|string|max:1000',

            // Optional promo code - validated against the gross subtotal
            'promo_code'              => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'services.required' => 'At least one service is required.',
            'services.array' => 'Services must be an array.',
            'services.min' => 'At least one service is required.',
            'services.max' => 'Cannot book more than 10 services at once.',
            'services.*.service_id.required' => 'Service ID is required for each service.',
            'services.*.service_id.exists' => 'One or more selected services are invalid.',
            'services.*.quantity.required' => 'Quantity is required for each service.',
            'services.*.quantity.integer' => 'Quantity must be a valid number.',
            'services.*.quantity.min' => 'Quantity must be at least 1.',
            'services.*.quantity.max' => 'Quantity cannot exceed 10 for any service.',
            'scheduled_at.date' => 'The scheduled time must be a valid date.',
            'address_id.required' => 'Please select a saved address.',
            'address_id.exists' => 'The selected address is invalid.',
            'preferred_partner_id.exists' => 'The selected partner is invalid or inactive.',
            'partner_selection_reason.in' => 'Invalid partner selection reason.',
        ];
    }
}
