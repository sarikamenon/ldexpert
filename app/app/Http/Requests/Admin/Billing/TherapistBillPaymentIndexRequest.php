<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use Illuminate\Foundation\Http\FormRequest;

class TherapistBillPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'method' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}

