<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PositionFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>|string> */
    protected function baseRules(?int $positionId = null): array
    {
        $nameRule = Rule::unique('positions', 'name');
        if ($positionId) {
            $nameRule = $nameRule->ignore($positionId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $nameRule],
        ];
    }
}
