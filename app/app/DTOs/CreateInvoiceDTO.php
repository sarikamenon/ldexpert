<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateInvoiceDTO
{
    /**
     * @param  array<int>  $sessionLogIds
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly string $invoiceDate,
        public readonly ?string $invoiceNumber,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly array $sessionLogIds,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            schoolId: (int) $data['school_id'],
            invoiceDate: $data['invoice_date'],
            invoiceNumber: $data['invoice_number'] ?? null,
            billingPeriodStart: $data['billing_period_start'],
            billingPeriodEnd: $data['billing_period_end'],
            sessionLogIds: isset($data['session_log_ids']) && is_array($data['session_log_ids'])
                ? array_map(fn ($id) => (int) $id, $data['session_log_ids'])
                : [],
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
            'school_id' => $this->schoolId,
            'invoice_date' => $this->invoiceDate,
            'invoice_number' => $this->invoiceNumber,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'session_log_ids' => $this->sessionLogIds,
            'notes' => $this->notes,
        ];
    }
}
