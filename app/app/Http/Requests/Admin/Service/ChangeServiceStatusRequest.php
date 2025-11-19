<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(static fn(ServiceStatus $status) => $status->value, ServiceStatus::cases());

        return [
            'status' => ['required', Rule::in($statuses)],
        ];
    }
}
