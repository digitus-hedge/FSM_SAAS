<?php
// app/Http/Requests/Auth/LoginRequest.php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Accepts email OR phone — the service decides which.
            'identifier' => ['required', 'string', 'max:190'],
            'password'   => ['required', 'string'],
            'remember'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        // Only one message. Never leak which field was empty.
        return [
            'identifier.required' => __('Email, phone or password is incorrect.'),
            'password.required'   => __('Email, phone or password is incorrect.'),
        ];
    }
}