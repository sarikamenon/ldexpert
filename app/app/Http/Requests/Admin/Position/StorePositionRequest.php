<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

final class StorePositionRequest extends PositionFormRequest
{
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', 'exists:services,id'],
        ]);
    }

    public function messages(): array
    {
        return [
            'service_ids.required' => 'Please select at least one service.',
            'service_ids.min' => 'Please select at least one service.',
        ];
    }
}
