<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class LedgerAccountsExportRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:schools,therapists'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}

