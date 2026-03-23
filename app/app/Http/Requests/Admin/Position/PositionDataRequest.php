<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

use App\Enums\PositionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PositionDataRequest extends FormRequest
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
                static fn (PositionStatus $status): string => $status->value,
                PositionStatus::cases()
            ))],
        ];
    }
}
