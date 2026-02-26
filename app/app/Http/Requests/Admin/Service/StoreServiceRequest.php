<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

final class StoreServiceRequest extends ServiceFormRequest
{
    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
