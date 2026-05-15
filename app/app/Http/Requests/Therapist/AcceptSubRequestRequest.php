<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptSubRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTherapist() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
