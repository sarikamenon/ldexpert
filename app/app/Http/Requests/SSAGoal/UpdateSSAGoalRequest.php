<?php

declare(strict_types=1);

namespace App\Http\Requests\SSAGoal;

use App\Models\SSAGoal;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSSAGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        if (! $goal instanceof SSAGoal) {
            return false;
        }

        return $this->user()?->can('update', $goal) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:50'],
            'goal' => ['required', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
