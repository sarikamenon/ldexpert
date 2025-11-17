<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

final class StoreSchoolRequest extends SchoolFormRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
