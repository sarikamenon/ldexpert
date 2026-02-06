<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use Illuminate\Foundation\Http\FormRequest;

final class SendTherapistBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email:rfc,dns'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
