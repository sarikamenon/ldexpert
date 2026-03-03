<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudentScheduleDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter_student_id' => ['required', 'integer', 'exists:users,id'],
            'filter_date' => ['nullable', 'date'],
            'filter_status' => ['nullable', Rule::in(array_map(fn (\BackedEnum $e) => $e->value, ScheduleStatus::cases()))],
            'filter_billing_status' => ['nullable', Rule::in(array_map(fn (\BackedEnum $e) => $e->value, BillingStatus::cases()))],
            'filter_ssa_id' => ['nullable', 'integer', 'exists:service_support_agreements,id'],
            'filter_therapist_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
