<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeadNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.max' => 'This note is too long to save. Try shortening it or split it across multiple notes.',
        ];
    }
}
