<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:50',
            'state' => 'required|string|max:50', // Province in Nepal
            'postal_code' => 'required|string|max:20', // Nepal postal codes are 5 digits
            'country' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:26,31', // Nepal's latitude range
            'longitude' => 'nullable|numeric|between:80,89', // Nepal's longitude range
            'address_type' => 'nullable|in:home,work,other',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'address_line_1.required' => 'Address line 1 is required.',
            'city.required' => 'City is required.',
            'state.required' => 'Province is required.',
            'postal_code.required' => 'Postal code is required.',
            'latitude.between' => 'Latitude must be within Nepal\'s boundaries (26-31).',
            'longitude.between' => 'Longitude must be within Nepal\'s boundaries (80-89).',
            'address_type.in' => 'Address type must be home, work, or other.',
        ];
    }
}
