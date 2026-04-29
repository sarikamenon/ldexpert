<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ledger;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateLedgerAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::ADMIN;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->route('type'),
            'account_id' => $this->route('id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:school,therapist'],
            'account_id' => ['required', 'integer'],
            'transaction_type' => ['required', 'string', 'in:credit_note,refund'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $accountId = $this->input('account_id');

            if (! $accountId || ! in_array($type, ['school', 'therapist'], true)) {
                return;
            }

            if ($type === 'school') {
                if (! School::query()->whereKey($accountId)->exists()) {
                    $validator->getMessageBag()->add('account_id', 'The selected school does not exist.');
                }

                return;
            }

            if (! User::query()
                ->whereKey($accountId)
                ->where('role', Role::THERAPIST->value)
                ->exists()) {
                $validator->getMessageBag()->add('account_id', 'The selected therapist does not exist.');
            }
        });
    }
}
