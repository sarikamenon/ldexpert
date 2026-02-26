<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

final class StoreTherapistRequest extends TherapistFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
