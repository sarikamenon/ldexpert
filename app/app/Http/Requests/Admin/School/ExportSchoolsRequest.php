<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

final class ExportSchoolsRequest extends SchoolFilterRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->filterRules();
    }
}
