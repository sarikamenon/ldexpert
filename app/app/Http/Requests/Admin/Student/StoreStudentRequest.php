<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

final class StoreStudentRequest extends StudentFormRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
