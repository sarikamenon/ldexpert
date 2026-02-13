<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Invoice;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admins can record payments
        /** @var User|null $user */
        $user = $this->user();

        return $user instanceof User && $user->role === Role::ADMIN->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_at.required' => 'Payment date is required.',
            'paid_at.date' => 'Payment date must be a valid date.',
            'paid_at.before_or_equal' => 'Payment date cannot be in the future.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a number.',
            'amount.min' => 'Payment amount must be greater than zero.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Invalid payment method selected.',
        ];
    }
}
