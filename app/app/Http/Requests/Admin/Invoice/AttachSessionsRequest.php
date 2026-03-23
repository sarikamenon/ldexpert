<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use App\Models\Invoice;
use App\Models\SessionLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttachSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');
        if (! $invoice instanceof Invoice) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = $this->user();

        return $user->can('update', $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_log_ids' => ['nullable', 'array'],
            'session_log_ids.*' => ['required', 'integer', Rule::exists('session_logs', 'id')],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $invoice = $this->route('invoice');
            if (! $invoice instanceof Invoice || ! $this->has('session_log_ids')) {
                return;
            }

            $sessionLogIds = $this->input('session_log_ids', []);
            if (empty($sessionLogIds)) {
                return;
            }

            $invalidCount = SessionLog::query()
                ->whereIn('id', $sessionLogIds)
                ->where('school_id', '!=', $invoice->school_id)
                ->count();

            if ($invalidCount > 0) {
                $validator->errors()->add(
                    'session_log_ids',
                    'All selected session logs must belong to the invoice school.'
                );
            }
        });
    }
}
