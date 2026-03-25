<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist\QGlob;

use App\Domain\QGlobRequest\Services\QGlobRequestService;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreQGlobRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::THERAPIST;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'student_id.required' => 'Please select a student.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $user = $this->user();
            if (! $user || $user->role !== Role::THERAPIST) {
                return;
            }

            $studentId = (int) $this->input('student_id', 0);
            if ($studentId <= 0) {
                return;
            }

            /** @var QGlobRequestService $service */
            $service = app(QGlobRequestService::class);
            if (! $service->studentIsEligibleForTherapist($studentId, $user->id)) {
                $v->errors()->add('student_id', 'The selected student is not eligible for QGlob requests.');
            }
        });
    }
}
