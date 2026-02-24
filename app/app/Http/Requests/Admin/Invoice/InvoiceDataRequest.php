<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InvoiceDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'filter_status' => ['nullable', Rule::in(InvoiceStatus::values())],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
            'filter_invoice_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}

