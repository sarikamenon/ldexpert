<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LedgerAccountsDataRequest extends FormRequest
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
            'filter_type' => ['required', 'string', Rule::in(['schools', 'therapists'])],
            'filter_search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
