<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

use App\Enums\PositionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangePositionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        $statuses = array_map(static fn (PositionStatus $status) => $status->value, PositionStatus::cases());

        return [
            'status' => ['required', Rule::in($statuses)],
        ];
    }
}
