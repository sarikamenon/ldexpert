<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(LeadStatus::options())],
            'status_reason' => ['nullable', 'string', 'max:1000'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
