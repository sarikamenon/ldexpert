<?php

declare(strict_types=1);

namespace App\DTOs;

final class BillingRunResultDTO
{
    public function __construct(
        public readonly int $billingScheduleId,
        public readonly string $status,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly int $sessionsFound,
        public readonly int $sessionsFromPriorPeriods,
        public readonly int $adjustmentsCount,
        public readonly float $adjustmentTotal,
        public readonly float $carryForwardAmount,
        public readonly ?int $invoiceId,
        public readonly ?int $therapistBillId,
        public readonly ?float $totalAmount,
        public readonly bool $autoSent,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * Return a copy with the auto-sent flag set (the DTO is immutable).
     */
    public function withAutoSent(bool $autoSent): self
    {
        return new self(
            billingScheduleId: $this->billingScheduleId,
            status: $this->status,
            billingPeriodStart: $this->billingPeriodStart,
            billingPeriodEnd: $this->billingPeriodEnd,
            sessionsFound: $this->sessionsFound,
            sessionsFromPriorPeriods: $this->sessionsFromPriorPeriods,
            adjustmentsCount: $this->adjustmentsCount,
            adjustmentTotal: $this->adjustmentTotal,
            carryForwardAmount: $this->carryForwardAmount,
            invoiceId: $this->invoiceId,
            therapistBillId: $this->therapistBillId,
            totalAmount: $this->totalAmount,
            autoSent: $autoSent,
            errorMessage: $this->errorMessage,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            billingScheduleId: (int) $data['billing_schedule_id'],
            status: (string) $data['status'],
            billingPeriodStart: (string) $data['billing_period_start'],
            billingPeriodEnd: (string) $data['billing_period_end'],
            sessionsFound: (int) ($data['sessions_found'] ?? 0),
            sessionsFromPriorPeriods: (int) ($data['sessions_from_prior_periods'] ?? 0),
            adjustmentsCount: (int) ($data['adjustments_count'] ?? 0),
            adjustmentTotal: (float) ($data['adjustment_total'] ?? 0),
            carryForwardAmount: (float) ($data['carry_forward_amount'] ?? 0),
            invoiceId: isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            therapistBillId: isset($data['therapist_bill_id']) ? (int) $data['therapist_bill_id'] : null,
            totalAmount: isset($data['total_amount']) ? (float) $data['total_amount'] : null,
            autoSent: (bool) ($data['auto_sent'] ?? false),
            errorMessage: $data['error_message'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'billing_schedule_id' => $this->billingScheduleId,
            'status' => $this->status,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'sessions_found' => $this->sessionsFound,
            'sessions_from_prior_periods' => $this->sessionsFromPriorPeriods,
            'adjustments_count' => $this->adjustmentsCount,
            'adjustment_total' => $this->adjustmentTotal,
            'carry_forward_amount' => $this->carryForwardAmount,
            'invoice_id' => $this->invoiceId,
            'therapist_bill_id' => $this->therapistBillId,
            'total_amount' => $this->totalAmount,
            'auto_sent' => $this->autoSent,
            'error_message' => $this->errorMessage,
        ];
    }
}
