<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use Illuminate\Validation\Rule;

final class StoreTherapistContractRequest extends TherapistContractFormRequest
{
    public function rules(): array
    {
        return [
            'therapist_id' => [
                'required',
                'integer',
                Rule::exists('therapist_profiles', 'id')
                    ->whereNull('deleted_at'),
            ],
        ] + $this->baseRules();
    }
}

