<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use App\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TherapistContractDataRequest extends FormRequest
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
            'filter_status' => ['nullable', Rule::in(ContractStatus::values())],
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_therapist_ids' => ['nullable', 'array'],
            'filter_therapist_ids.*' => ['integer', 'exists:therapist_profiles,id'],
        ];
    }
}
