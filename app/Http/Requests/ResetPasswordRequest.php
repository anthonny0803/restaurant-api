<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'                 => ['required', 'string', 'email'],
            'token'                 => ['required', 'string'],
            'password'              => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
