<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use App\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeTherapistContractStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ContractStatus::values())],
        ];
    }
}
