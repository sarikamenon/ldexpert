<?php

declare(strict_types=1);

namespace App\DTOs;

final class BillingScheduleDTO
{
    public function __construct(
        public readonly string $schedulableType,
        public readonly int $schedulableId,
        public readonly string $scheduleType,
        public readonly string $billingMode,
        public readonly string $frequency,
        public readonly string $generationDayType,
        public readonly ?int $generationDayOfWeek,
        public readonly ?int $generationDelayDays,
        public readonly int $minGraceDays = 2,
        public readonly int $paymentTermsDays = 30,
        public readonly bool $autoGenerate = true,
        public readonly bool $autoSend = false,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schedulableType: (string) $data['schedulable_type'],
            schedulableId: (int) $data['schedulable_id'],
            scheduleType: (string) $data['schedule_type'],
            billingMode: (string) ($data['billing_mode'] ?? 'standard'),
            frequency: (string) ($data['frequency'] ?? 'semi_monthly'),
            generationDayType: (string) ($data['generation_day_type'] ?? 'day_of_week'),
            generationDayOfWeek: isset($data['generation_day_of_week']) ? (int) $data['generation_day_of_week'] : null,
            generationDelayDays: isset($data['generation_delay_days']) ? (int) $data['generation_delay_days'] : null,
            minGraceDays: (int) ($data['min_grace_days'] ?? 2),
            paymentTermsDays: (int) ($data['payment_terms_days'] ?? 30),
            autoGenerate: (bool) ($data['auto_generate'] ?? true),
            autoSend: (bool) ($data['auto_send'] ?? false),
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedulable_type' => $this->schedulableType,
            'schedulable_id' => $this->schedulableId,
            'schedule_type' => $this->scheduleType,
            'billing_mode' => $this->billingMode,
            'frequency' => $this->frequency,
            'generation_day_type' => $this->generationDayType,
            'generation_day_of_week' => $this->generationDayOfWeek,
            'generation_delay_days' => $this->generationDelayDays,
            'min_grace_days' => $this->minGraceDays,
            'payment_terms_days' => $this->paymentTermsDays,
            'auto_generate' => $this->autoGenerate,
            'auto_send' => $this->autoSend,
            'notes' => $this->notes,
        ];
    }
}
