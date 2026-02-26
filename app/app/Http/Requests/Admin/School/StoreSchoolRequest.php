<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

final class StoreSchoolRequest extends SchoolFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
