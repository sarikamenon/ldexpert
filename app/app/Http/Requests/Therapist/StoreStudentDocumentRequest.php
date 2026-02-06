<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\DocumentType;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        if (! $student instanceof User) {
            return false;
        }

        return $this->user()->can('create', [StudentDocument::class, $student]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'document_type' => ['required', 'string', Rule::enum(DocumentType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
