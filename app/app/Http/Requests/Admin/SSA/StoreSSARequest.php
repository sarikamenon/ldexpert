<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\SSA;

final class StoreSSARequest extends SSAFormRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}

