<?php

declare(strict_types=1);

namespace App\DTOs;

final class InvoiceLineItemDTO
{
    public function __construct(
        public readonly string $lineType,
        public readonly string $description,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $total,
        public readonly int $sortOrder = 0,
        public readonly ?int $scheduleId = null,
        public readonly ?int $sessionLogId = null,
        public readonly ?int $sourceInvoiceId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lineType: (string) $data['line_type'],
            description: (string) $data['description'],
            billingPeriodStart: (string) $data['billing_period_start'],
            billingPeriodEnd: (string) $data['billing_period_end'],
            quantity: (float) ($data['quantity'] ?? 1),
            unitPrice: (float) $data['unit_price'],
            total: (float) $data['total'],
            sortOrder: (int) ($data['sort_order'] ?? 0),
            scheduleId: isset($data['schedule_id']) ? (int) $data['schedule_id'] : null,
            sessionLogId: isset($data['session_log_id']) ? (int) $data['session_log_id'] : null,
            sourceInvoiceId: isset($data['source_invoice_id']) ? (int) $data['source_invoice_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line_type' => $this->lineType,
            'description' => $this->description,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total' => $this->total,
            'sort_order' => $this->sortOrder,
            'schedule_id' => $this->scheduleId,
            'session_log_id' => $this->sessionLogId,
            'source_invoice_id' => $this->sourceInvoiceId,
        ];
    }
}
