<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

final class StorePositionRequest extends PositionFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', 'exists:services,id'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_ids.required' => 'Please select at least one service.',
            'service_ids.min' => 'Please select at least one service.',
        ];
    }
}
