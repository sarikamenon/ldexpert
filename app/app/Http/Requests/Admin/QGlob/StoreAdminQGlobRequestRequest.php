<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QGlob;

use App\Domain\QGlobRequest\Services\QGlobRequestService;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreAdminQGlobRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::ADMIN;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'therapist_id' => ['required', 'integer', 'exists:users,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'requested_date' => ['required', 'date'],
            'requested_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'therapist_id.required' => 'Please select a therapist.',
            'student_id.required' => 'Please select a student.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $therapistId = (int) $this->input('therapist_id', 0);
            $studentId = (int) $this->input('student_id', 0);

            if ($therapistId <= 0) {
                return;
            }

            $therapist = User::query()->find($therapistId);
            if ($therapist && $therapist->role !== Role::THERAPIST) {
                $v->errors()->add('therapist_id', 'The selected user must be a therapist.');
            }

            if ($studentId <= 0) {
                return;
            }

            /** @var QGlobRequestService $service */
            $service = app(QGlobRequestService::class);
            if (! $service->studentIsEligibleForTherapist($studentId, $therapistId)) {
                $v->errors()->add('student_id', 'The selected student is not eligible for QGlob requests for this therapist.');
            }
        });
    }
}
