<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use App\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SchoolContractDataRequest extends FormRequest
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
            'filter_school_ids' => ['nullable', 'array'],
            'filter_school_ids.*' => ['integer', Rule::exists('schools', 'id')],
            'filter_school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ];
    }
}
