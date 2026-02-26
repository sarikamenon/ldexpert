<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

use App\Enums\SchoolStatus;
use Illuminate\Validation\Rule;

final class StoreSchoolContractRequest extends SchoolContractFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'school_id' => [
                'required',
                'integer',
                Rule::exists('schools', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('status', SchoolStatus::ACTIVE->value)),
            ],
        ] + $this->baseRules();
    }
}
