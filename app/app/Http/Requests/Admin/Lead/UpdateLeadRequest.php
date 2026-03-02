<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Lead;

final class UpdateLeadRequest extends LeadFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
