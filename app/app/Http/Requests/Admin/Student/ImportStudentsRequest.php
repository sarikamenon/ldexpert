<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\StudentImportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('student-import.settings.max_file_size', 10240);
        $allowedMimes = config('student-import.settings.allowed_mime_types', ['text/csv', 'text/plain', 'application/csv']);

        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'mimetypes:' . implode(',', $allowedMimes),
                'max:' . $maxSize,
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in(array_column(\App\Enums\StudentImportType::cases(), 'value')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to import.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.mimetypes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed ' . config('student-import.settings.max_file_size', 10240) . ' KB.',
            'type.in' => 'Invalid import type. Must be one of: NOVA, RSM, MARVIN.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Default to NOVA if type is not provided
        if (! $this->has('type')) {
            $this->merge(['type' => StudentImportType::NOVA->value]);
        }
    }
}
