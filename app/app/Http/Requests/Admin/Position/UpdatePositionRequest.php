<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Position;

use App\Models\Position;

final class UpdatePositionRequest extends PositionFormRequest
{
    public function rules(): array
    {
        /** @var Position|null $position */
        $position = $this->route('position');

        return $this->baseRules($position?->id);
    }
}
