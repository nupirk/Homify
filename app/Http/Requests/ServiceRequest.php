<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }
    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'category_id'     => 'required|exists:service_categories,category_id',
            'image_url'       => 'nullable|url',
            'features'        => 'nullable|array',
            'terms_conditions' => 'nullable|string',
            'is_active'       => 'boolean',
            'requires_prepay' => 'boolean',
        ];
    }
}
