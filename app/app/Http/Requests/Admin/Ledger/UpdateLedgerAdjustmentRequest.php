<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLedgerAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::ADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'recorded_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recorded_at.before_or_equal' => 'Transaction date cannot be in the future.',
        ];
    }
}
