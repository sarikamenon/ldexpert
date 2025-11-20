<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSSARequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(static fn(SSAStatus $status) => $status->value, SSAStatus::cases());

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($statuses)],
            'student_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'therapist_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

