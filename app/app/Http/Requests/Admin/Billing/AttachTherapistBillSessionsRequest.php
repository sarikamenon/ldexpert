<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Models\SessionLog;
use App\Models\TherapistBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttachTherapistBillSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('bill');
        if (! $bill instanceof TherapistBill) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = $this->user();

        return $user->can('update', $bill);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_log_ids' => ['nullable', 'array'],
            'session_log_ids.*' => ['integer', Rule::exists('session_logs', 'id')],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $bill = $this->route('bill');
            if (! $bill instanceof TherapistBill || ! $this->has('session_log_ids')) {
                return;
            }

            $sessionLogIds = $this->input('session_log_ids', []);
            if (empty($sessionLogIds) || ! is_array($sessionLogIds)) {
                return;
            }

            $invalidCount = SessionLog::query()
                ->whereIn('id', $sessionLogIds)
                ->where('therapist_id', '!=', $bill->therapist_id)
                ->count();

            if ($invalidCount > 0) {
                $validator->errors()->add(
                    'session_log_ids',
                    'All selected session logs must belong to the selected therapist.'
                );
            }
        });
    }
}

