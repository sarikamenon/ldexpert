<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LedgerAccountTransactionsDataRequest extends FormRequest
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
            'filter_type' => ['required', 'string', Rule::in(['school', 'therapist'])],
            'filter_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
