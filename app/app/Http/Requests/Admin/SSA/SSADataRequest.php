<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SSADataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'filter_search' => ['nullable', 'string', 'max:255'],
            'filter_status' => ['nullable', Rule::in(array_map(
                static fn (SSAStatus $status): string => $status->value,
                SSAStatus::cases()
            ))],
            'filter_student_id' => ['nullable', 'integer'],
            'filter_service_id' => ['nullable', 'integer'],
            'filter_therapist_id' => ['nullable', 'integer'],
        ];
    }
}
