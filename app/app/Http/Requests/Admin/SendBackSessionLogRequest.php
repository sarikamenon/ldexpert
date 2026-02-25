<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SendBackSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\SessionLog|null $sessionLog */
        $sessionLog = $this->route('sessionLog');

        return $sessionLog !== null && ($this->user()?->can('sendBack', $sessionLog) ?? false);
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
