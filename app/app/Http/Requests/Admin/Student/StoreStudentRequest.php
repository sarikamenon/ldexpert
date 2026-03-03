<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Student;

final class StoreStudentRequest extends StudentFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
