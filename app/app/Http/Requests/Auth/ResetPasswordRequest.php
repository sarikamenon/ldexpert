<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'token' => ['required'],
            'username' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}
