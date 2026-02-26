<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class PayStubReportDataRequest extends FormRequest
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
            'filter_year' => ['required', 'integer', 'min:2026', 'max:'.$currentYear],
        ];
    }
}
