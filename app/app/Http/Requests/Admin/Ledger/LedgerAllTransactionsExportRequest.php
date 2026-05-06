<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use App\Enums\CashDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LedgerAllTransactionsExportRequest extends FormRequest
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
            'filter_date_from'    => ['nullable', 'date'],
            'filter_date_to'      => ['nullable', 'date'],
            'filter_direction'    => ['nullable', 'string', Rule::in(array_map(fn (CashDirection $d) => $d->value, CashDirection::cases()))],
            'filter_school_id'    => ['nullable', 'integer', 'min:1'],
            'filter_therapist_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
