<?php

namespace App\Http\Requests;

use App\Support\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lowercase a submitted email so a differently-cased address still logs
     * in. Phone numbers pass through unchanged (strtolower is a no-op there).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => EmailAddress::normalize($this->input('email'))]);
        }
    }
    public function rules(): array
    {
        return [
            'email'    => 'required|string', // Can be email or phone
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Email or phone number is required.',
            'password.required' => 'Password is required.',
            'password.min'      => 'Password must be at least 8 characters long.',
        ];
    }
}
