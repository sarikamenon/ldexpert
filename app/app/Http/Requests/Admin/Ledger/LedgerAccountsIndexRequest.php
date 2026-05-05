<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class LedgerAccountsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            $this->merge(['type' => 'schools']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:schools,therapists,all-transactions'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
