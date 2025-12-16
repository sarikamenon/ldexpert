<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use App\Enums\SSAStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeSSAStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(static fn (SSAStatus $status) => $status->value, SSAStatus::cases());

        return [
            'status' => ['required', Rule::in($statuses)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
