<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateInvoiceDTO
{
    /**
     * @param array<int> $sessionLogIds
     */
    public function __construct(
        public readonly ?int $schoolId,
        public readonly string $billingPeriodStart,
        public readonly string $billingPeriodEnd,
        public readonly array $sessionLogIds,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
            billingPeriodStart: $data['billing_period_start'],
            billingPeriodEnd: $data['billing_period_end'],
            sessionLogIds: isset($data['session_log_ids']) && is_array($data['session_log_ids'])
                ? array_map(fn($id) => (int) $id, $data['session_log_ids'])
                : [],
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'billing_period_start' => $this->billingPeriodStart,
            'billing_period_end' => $this->billingPeriodEnd,
            'session_log_ids' => $this->sessionLogIds,
            'notes' => $this->notes,
        ];
    }
}
