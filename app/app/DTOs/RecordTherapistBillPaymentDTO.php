<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentMethod;

final readonly class RecordTherapistBillPaymentDTO
{
    public function __construct(
        public int $therapistBillId,
        public string $paidAt,
        public float $amount,
        public PaymentMethod $method,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?int $recordedById = null,
        public ?int $therapistId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            therapistBillId: (int) $data['therapist_bill_id'],
            paidAt: $data['paid_at'],
            amount: (float) $data['amount'],
            method: PaymentMethod::from($data['method']),
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            recordedById: $data['recorded_by_id'] ?? null,
            therapistId: isset($data['therapist_id']) ? (int) $data['therapist_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'therapist_bill_id' => $this->therapistBillId,
            'paid_at' => $this->paidAt,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recorded_by_id' => $this->recordedById,
            'therapist_id' => $this->therapistId,
        ];
    }
}
