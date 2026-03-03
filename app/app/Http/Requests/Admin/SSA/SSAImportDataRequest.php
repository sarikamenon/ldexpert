<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

use Illuminate\Foundation\Http\FormRequest;

final class SSAImportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
