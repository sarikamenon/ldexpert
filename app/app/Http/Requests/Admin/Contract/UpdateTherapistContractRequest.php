<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

final class UpdateTherapistContractRequest extends TherapistContractFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
