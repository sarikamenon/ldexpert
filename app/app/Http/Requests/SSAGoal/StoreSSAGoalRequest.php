<?php

declare(strict_types=1);

namespace App\Http\Requests\SSAGoal;

use App\Http\Support\SSAGoalReturnTo;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreSSAGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ssa = $this->route('ssa');

        if (! $ssa instanceof ServiceSupportAgreement) {
            return false;
        }

        return $this->user()?->can('create', [SSAGoal::class, $ssa]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:50'],
            'objective' => ['required', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:1000'],
            'return_to' => ['nullable', new Enum(SSAGoalReturnTo::class)],
        ];
    }
}
