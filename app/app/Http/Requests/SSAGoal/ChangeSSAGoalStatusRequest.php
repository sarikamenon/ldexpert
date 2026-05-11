<?php

declare(strict_types=1);

namespace App\Http\Requests\SSAGoal;

use App\Enums\SSAGoalStatus;
use App\Models\SSAGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeSSAGoalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        if (! $goal instanceof SSAGoal) {
            return false;
        }

        return $this->user()?->can('changeStatus', $goal) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                SSAGoalStatus::MASTERED->value,
                SSAGoalStatus::DISCONTINUED->value,
            ])],
        ];
    }
}
