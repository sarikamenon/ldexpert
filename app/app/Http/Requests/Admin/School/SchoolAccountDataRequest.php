<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use Illuminate\Foundation\Http\FormRequest;

final class SchoolAccountDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'draw' => ['nullable', 'integer'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer'],
        ];
    }
}
