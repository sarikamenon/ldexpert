<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateTherapistBillDTO
{
    /**
     * @param  array<int>  $sessionLogIds
     */
    public function __construct(
        public readonly int $therapistId,
        public readonly string $billDate,
        public readonly ?string $billNumber,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly array $sessionLogIds,
        public readonly ?string $dueDate = null,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            therapistId: (int) $data['therapist_id'],
            billDate: $data['bill_date'],
            billNumber: $data['bill_number'] ?? null,
            billingPeriodStart: $data['billing_period_start'],
            billingPeriodEnd: $data['billing_period_end'],
            sessionLogIds: isset($data['session_log_ids']) && is_array($data['session_log_ids'])
                ? array_map(fn ($id) => (int) $id, $data['session_log_ids'])
                : [],
            dueDate: $data['due_date'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'bill_date' => $this->billDate,
            'bill_number' => $this->billNumber,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'session_log_ids' => $this->sessionLogIds,
            'due_date' => $this->dueDate,
            'notes' => $this->notes,
        ];
    }
}
