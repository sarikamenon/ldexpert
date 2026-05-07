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
            'note' => ['required', 'string'],
        ];
    }
}
