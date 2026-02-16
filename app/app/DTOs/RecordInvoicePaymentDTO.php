<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentMethod;

final readonly class RecordInvoicePaymentDTO
{
    public function __construct(
        public int $invoiceId,
        public string $paidAt,
        public float $amount,
        public PaymentMethod $method,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?int $recordedById = null,
        public ?int $schoolId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceId: (int) $data['invoice_id'],
            paidAt: $data['paid_at'],
            amount: (float) $data['amount'],
            method: PaymentMethod::from($data['method']),
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            recordedById: $data['recorded_by_id'] ?? null,
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'paid_at' => $this->paidAt,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recorded_by_id' => $this->recordedById,
            'school_id' => $this->schoolId,
        ];
    }
}
