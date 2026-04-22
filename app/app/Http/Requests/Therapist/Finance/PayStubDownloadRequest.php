<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class PayStubDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $currentYear = (int) date('Y');

        return [
            'year' => ['required', 'integer', 'min:2025', 'max:'.$currentYear],
        ];
    }
}
