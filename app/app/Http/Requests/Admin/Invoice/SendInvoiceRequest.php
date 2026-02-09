<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use Illuminate\Foundation\Http\FormRequest;

final class SendInvoiceRequest extends FormRequest
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
