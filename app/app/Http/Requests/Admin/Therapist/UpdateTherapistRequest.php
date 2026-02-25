<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Therapist;

final class UpdateTherapistRequest extends TherapistFormRequest
{
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->route('therapist');

        return $this->baseRules($user->id);
    }
}
