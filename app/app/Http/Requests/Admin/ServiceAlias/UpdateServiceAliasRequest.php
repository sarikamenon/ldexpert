<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceAlias;

use App\Enums\SSAImportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        /** @var \App\Models\ServiceAlias|null $alias */
        $alias = $this->route('service_alias');
        $aliasId = $alias?->id;

        return [
            'source' => ['required', 'string', Rule::in(SSAImportType::values())],
            'external_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_aliases')->where(function ($query): void {
                    $query->where('source', $this->input('source'));
                })->ignore($aliasId),
            ],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'external_name.unique' => 'This external name already exists for the selected source.',
        ];
    }
}
