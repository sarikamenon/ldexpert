<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Enums\DocumentType;
use App\Models\StudentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudentDocumentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', StudentDocument::class);
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'document_type' => ['nullable', 'string', Rule::enum(DocumentType::class)],
            'uploaded_by_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
