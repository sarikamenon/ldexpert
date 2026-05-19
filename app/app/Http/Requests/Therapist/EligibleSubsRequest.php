<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class EligibleSubsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTherapist() === true;
    }

    /**
     * Three input modes for the eligible-sub picker:
     *  - Edit-time with sub-request row: route binds {subRequest}; no query params required.
     *  - Edit-no-request: requires schedule_id.
     *  - Create-time: requires service_id and date.
     *
     * Each field is independently optional; the controller branches on what is
     * provided. Validation only enforces type/format when a field is present.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schedule_id' => ['sometimes', 'integer', 'exists:schedules,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date' => ['sometimes', 'string', 'date_format:Y-m-d'],
        ];
    }
}
