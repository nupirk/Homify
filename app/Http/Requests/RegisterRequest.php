<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Store emails in one canonical form so "John@X.com" and "john@x.com"
     * cannot become two accounts (and so login/reset find the same row).
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
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            // Case-insensitive uniqueness: rows created before emails were
            // normalized may hold mixed case, and a plain `unique` on
            // PostgreSQL would let "John@X.com" register beside "john@x.com".
            'email'      => [
                'required',
                'email',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (User::whereRaw('LOWER(email) = ?', [EmailAddress::normalize((string) $value)])->exists()) {
                        $fail('This email address is already registered.');
                    }
                },
            ],
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
