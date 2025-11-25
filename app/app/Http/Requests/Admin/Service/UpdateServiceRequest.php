<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Models\Service;

final class UpdateServiceRequest extends ServiceFormRequest
{
    public function rules(): array
    {
        /** @var Service|null $service */
        $service = $this->route('service');

        return $this->baseRules($service?->id);
    }
}
