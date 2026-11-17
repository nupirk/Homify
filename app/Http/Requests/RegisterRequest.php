<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'required|string|unique:users,phone',
            'password'   => 'required|string|min:8|confirmed',
            'user_type'  => ['nullable', Rule::in(['customer', 'partner'])],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'email.required'      => 'Email address is required.',
            'email.email'         => 'Please provide a valid email address.',
            'email.unique'        => 'This email address is already registered.',
            'phone.required'      => 'Phone number is required.',
            'phone.unique'        => 'This phone number is already registered.',
            'password.required'   => 'Password is required.',
            'password.min'        => 'Password must be at least 8 characters long.',
            'password.confirmed'  => 'Password confirmation does not match.',
            'user_type.in'        => 'User type must be either customer or partner.',
        ];
    }
}
