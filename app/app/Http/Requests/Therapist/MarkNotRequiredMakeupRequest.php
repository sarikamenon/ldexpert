<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class MarkNotRequiredMakeupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user !== null && $user->isTherapist();
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for marking this as not required.',
            'reason.max' => 'Reason may not be greater than :max characters.',
        ];
    }
}
