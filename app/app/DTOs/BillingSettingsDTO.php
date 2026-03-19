<?php

declare(strict_types=1);

namespace App\DTOs;

final class BillingSettingsDTO
{
    public function __construct(
        public readonly string $defaultFrequency,
        public readonly string $defaultGenerationDayType,
        public readonly int $defaultGenerationDayOfWeek,
        public readonly int $defaultMinGraceDays,
        public readonly int $defaultPaymentTermsDays,
        public readonly bool $defaultAutoGenerate,
        public readonly bool $defaultAutoSend,
        public readonly int $reminderDaysBeforeDue,
        public readonly int $reminderDaysAfterDue,
        public readonly int $reminderOverdueRepeatDays,
        public readonly int $maxOverdueReminders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            defaultFrequency: (string) ($data['default_frequency'] ?? 'semi_monthly'),
            defaultGenerationDayType: (string) ($data['default_generation_day_type'] ?? 'day_of_week'),
            defaultGenerationDayOfWeek: (int) ($data['default_generation_day_of_week'] ?? 2),
            defaultMinGraceDays: (int) ($data['default_min_grace_days'] ?? 2),
            defaultPaymentTermsDays: (int) ($data['default_payment_terms_days'] ?? 30),
            defaultAutoGenerate: (bool) ($data['default_auto_generate'] ?? true),
            defaultAutoSend: (bool) ($data['default_auto_send'] ?? false),
            reminderDaysBeforeDue: (int) ($data['reminder_days_before_due'] ?? 5),
            reminderDaysAfterDue: (int) ($data['reminder_days_after_due'] ?? 3),
            reminderOverdueRepeatDays: (int) ($data['reminder_overdue_repeat_days'] ?? 7),
            maxOverdueReminders: (int) ($data['max_overdue_reminders'] ?? 3),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'default_frequency' => $this->defaultFrequency,
            'default_generation_day_type' => $this->defaultGenerationDayType,
            'default_generation_day_of_week' => $this->defaultGenerationDayOfWeek,
            'default_min_grace_days' => $this->defaultMinGraceDays,
            'default_payment_terms_days' => $this->defaultPaymentTermsDays,
            'default_auto_generate' => $this->defaultAutoGenerate,
            'default_auto_send' => $this->defaultAutoSend,
            'reminder_days_before_due' => $this->reminderDaysBeforeDue,
            'reminder_days_after_due' => $this->reminderDaysAfterDue,
            'reminder_overdue_repeat_days' => $this->reminderOverdueRepeatDays,
            'max_overdue_reminders' => $this->maxOverdueReminders,
        ];
    }
}
