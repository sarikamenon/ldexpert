<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

final class StoreTherapistRequest extends TherapistFormRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
