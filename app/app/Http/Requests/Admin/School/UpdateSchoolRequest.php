<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\School;

use App\Models\School;

final class UpdateSchoolRequest extends SchoolFormRequest
{
    public function rules(): array
    {
        /** @var School|null $school */
        $school = $this->route('school');

        return $this->baseRules($school?->id);
    }
}
