<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

final class IndexSchoolRequest extends SchoolFilterRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), [
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
    }
}
