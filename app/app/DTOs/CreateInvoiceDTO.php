<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateInvoiceDTO
{
    /**
     * @param  array<int>  $sessionLogIds
     * @param  array<int>  $scheduleIds  Selected schedules for the advance branch (§6).
     * @param  int|null  $paymentTermsDays  Days from invoice date to due date; null = default terms.
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly string $invoiceDate,
        public readonly ?string $invoiceNumber,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly array $sessionLogIds,
        public readonly ?string $notes = null,
        public readonly array $scheduleIds = [],
        public readonly ?int $paymentTermsDays = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schoolId: (int) $data['school_id'],
            invoiceDate: $data['invoice_date'],
            invoiceNumber: $data['invoice_number'] ?? null,
            billingPeriodStart: $data['billing_period_start'],
            billingPeriodEnd: $data['billing_period_end'],
            sessionLogIds: isset($data['session_log_ids']) && is_array($data['session_log_ids'])
                ? collect($data['session_log_ids'])->map(fn ($id): int => (int) $id)->all()
                : [],
            notes: $data['notes'] ?? null,
            scheduleIds: isset($data['schedule_ids']) && is_array($data['schedule_ids'])
                ? collect($data['schedule_ids'])->map(fn ($id): int => (int) $id)->all()
                : [],
            paymentTermsDays: isset($data['payment_terms_days']) ? (int) $data['payment_terms_days'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
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
            'schedule_ids' => $this->scheduleIds,
            'payment_terms_days' => $this->paymentTermsDays,
        ];
    }
}
