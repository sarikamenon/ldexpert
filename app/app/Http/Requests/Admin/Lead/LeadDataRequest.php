<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LeadDataRequest extends FormRequest
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
            'filter_status' => ['nullable', Rule::in(LeadStatus::options())],
            'filter_source' => ['nullable', Rule::in(LeadSource::options())],
            'filter_school_id' => ['nullable', 'integer'],
        ];
    }
}
