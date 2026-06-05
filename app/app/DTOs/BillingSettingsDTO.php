<?php

declare(strict_types=1);

namespace App\DTOs;

final class BillingSettingsDTO
{
    public function __construct(
        public readonly string $defaultFrequency,
        public readonly string $defaultGenerationDayType,
        public readonly int $defaultGenerationDayOfWeek,
        public readonly int $defaultDelayDays,
        public readonly int $defaultPaymentTermsDays,
        public readonly bool $defaultAutoGenerate,
        public readonly bool $defaultAutoSend,
        public readonly string $advanceDefaultFrequency,
        public readonly string $advanceDefaultGenerationDayType,
        public readonly int $advanceDefaultGenerationDayOfWeek,
        public readonly int $advanceDefaultDelayDays,
        public readonly int $advanceDefaultPaymentTermsDays,
        public readonly bool $advanceDefaultAutoGenerate,
        public readonly bool $advanceDefaultAutoSend,
        public readonly string $standardDefaultFrequency,
        public readonly string $standardDefaultGenerationDayType,
        public readonly int $standardDefaultGenerationDayOfWeek,
        public readonly int $standardDefaultDelayDays,
        public readonly int $standardDefaultPaymentTermsDays,
        public readonly bool $standardDefaultAutoGenerate,
        public readonly bool $standardDefaultAutoSend,
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
            defaultDelayDays: (int) ($data['default_delay_days'] ?? 2),
            defaultPaymentTermsDays: (int) ($data['default_payment_terms_days'] ?? 30),
            defaultAutoGenerate: (bool) ($data['default_auto_generate'] ?? true),
            defaultAutoSend: (bool) ($data['default_auto_send'] ?? false),
            advanceDefaultFrequency: (string) ($data['advance_default_frequency'] ?? 'semi_monthly'),
            advanceDefaultGenerationDayType: (string) ($data['advance_default_generation_day_type'] ?? 'day_of_week'),
            advanceDefaultGenerationDayOfWeek: (int) ($data['advance_default_generation_day_of_week'] ?? 2),
            advanceDefaultDelayDays: (int) ($data['advance_default_delay_days'] ?? 2),
            advanceDefaultPaymentTermsDays: (int) ($data['advance_default_payment_terms_days'] ?? 30),
            advanceDefaultAutoGenerate: (bool) ($data['advance_default_auto_generate'] ?? true),
            advanceDefaultAutoSend: (bool) ($data['advance_default_auto_send'] ?? false),
            standardDefaultFrequency: (string) ($data['standard_default_frequency'] ?? 'semi_monthly'),
            standardDefaultGenerationDayType: (string) ($data['standard_default_generation_day_type'] ?? 'day_of_week'),
            standardDefaultGenerationDayOfWeek: (int) ($data['standard_default_generation_day_of_week'] ?? 2),
            standardDefaultDelayDays: (int) ($data['standard_default_delay_days'] ?? 2),
            standardDefaultPaymentTermsDays: (int) ($data['standard_default_payment_terms_days'] ?? 30),
            standardDefaultAutoGenerate: (bool) ($data['standard_default_auto_generate'] ?? true),
            standardDefaultAutoSend: (bool) ($data['standard_default_auto_send'] ?? false),
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
            'default_delay_days' => $this->defaultDelayDays,
            'default_payment_terms_days' => $this->defaultPaymentTermsDays,
            'default_auto_generate' => $this->defaultAutoGenerate,
            'default_auto_send' => $this->defaultAutoSend,
            'advance_default_frequency' => $this->advanceDefaultFrequency,
            'advance_default_generation_day_type' => $this->advanceDefaultGenerationDayType,
            'advance_default_generation_day_of_week' => $this->advanceDefaultGenerationDayOfWeek,
            'advance_default_delay_days' => $this->advanceDefaultDelayDays,
            'advance_default_payment_terms_days' => $this->advanceDefaultPaymentTermsDays,
            'advance_default_auto_generate' => $this->advanceDefaultAutoGenerate,
            'advance_default_auto_send' => $this->advanceDefaultAutoSend,
            'standard_default_frequency' => $this->standardDefaultFrequency,
            'standard_default_generation_day_type' => $this->standardDefaultGenerationDayType,
            'standard_default_generation_day_of_week' => $this->standardDefaultGenerationDayOfWeek,
            'standard_default_delay_days' => $this->standardDefaultDelayDays,
            'standard_default_payment_terms_days' => $this->standardDefaultPaymentTermsDays,
            'standard_default_auto_generate' => $this->standardDefaultAutoGenerate,
            'standard_default_auto_send' => $this->standardDefaultAutoSend,
            'reminder_days_before_due' => $this->reminderDaysBeforeDue,
            'reminder_days_after_due' => $this->reminderDaysAfterDue,
            'reminder_overdue_repeat_days' => $this->reminderOverdueRepeatDays,
            'max_overdue_reminders' => $this->maxOverdueReminders,
        ];
    }
}
