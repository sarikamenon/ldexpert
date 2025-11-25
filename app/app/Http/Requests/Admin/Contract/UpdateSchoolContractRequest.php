<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contract;

final class UpdateSchoolContractRequest extends SchoolContractFormRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
