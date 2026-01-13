<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\DocumentType;
use App\Models\StudentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSessionLogDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sessionLog = $this->route('sessionLog');

        // Verify therapist owns this session log
        if ($sessionLog->therapist_id !== $this->user()->id) {
            return false;
        }

        return $this->user()->can('create', [StudentDocument::class, $sessionLog]);
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
