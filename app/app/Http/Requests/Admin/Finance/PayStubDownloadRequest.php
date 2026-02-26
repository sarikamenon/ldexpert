<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'year' => ['required', 'integer', 'min:2026', 'max:'.$currentYear],
            'therapist_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
