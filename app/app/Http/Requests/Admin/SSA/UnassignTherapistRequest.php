<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use Illuminate\Foundation\Http\FormRequest;

final class UnassignTherapistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
