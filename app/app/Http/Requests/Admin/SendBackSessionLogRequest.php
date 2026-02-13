<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SendBackSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sendBack', $this->route('sessionLog'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Please provide a comment explaining what needs to be rectified.',
        ];
    }
}
