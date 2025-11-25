<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

use App\Models\User;

final class UpdateStudentRequest extends StudentFormRequest
{
    public function rules(): array
    {
        /** @var User $student */
        $student = $this->route('student');

        return $this->baseRules($student?->id);
    }
}
