<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class AddTherapistCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'therapist';
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
            'submit_after_comment' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'comment.required' => 'Please provide a comment.',
            'comment.min' => 'Comment must be at least :min characters.',
        ];
    }
}
