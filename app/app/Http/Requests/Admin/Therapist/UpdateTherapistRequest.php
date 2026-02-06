<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

final class UpdateTherapistRequest extends TherapistFormRequest
{
    public function rules(): array
    {
        $user = $this->route('therapist');

        return $this->baseRules($user?->id);
    }
}
