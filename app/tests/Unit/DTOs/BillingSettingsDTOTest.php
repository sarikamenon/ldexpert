<?php

declare(strict_types=1);

use App\DTOs\BillingSettingsDTO;

test('billing settings dto creates from array with all fields', function () {
    $data = [
        'default_frequency' => 'monthly',
        'default_generation_day_type' => 'fixed_delay',
        'default_generation_day_of_week' => 3,
        'default_delay_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => false,
        'default_auto_send' => true,
        'advance_default_frequency' => 'weekly',
        'advance_default_generation_day_type' => 'fixed_delay',
        'advance_default_generation_day_of_week' => 1,
        'advance_default_delay_days' => 3,
        'advance_default_payment_terms_days' => 15,
        'advance_default_auto_generate' => true,
        'advance_default_auto_send' => true,
        'standard_default_frequency' => 'bi_weekly',
        'standard_default_generation_day_type' => 'day_of_week',
        'standard_default_generation_day_of_week' => 4,
        'standard_default_delay_days' => 6,
        'standard_default_payment_terms_days' => 25,
        'standard_default_auto_generate' => false,
        'reminder_days_before_due' => 7,
        'reminder_days_after_due' => 5,
        'reminder_overdue_repeat_days' => 10,
        'max_overdue_reminders' => 5,
    ];

    $dto = BillingSettingsDTO::fromArray($data);

    expect($dto->defaultFrequency)->toBe('monthly')
        ->and($dto->defaultGenerationDayType)->toBe('fixed_delay')
        ->and($dto->defaultGenerationDayOfWeek)->toBe(3)
        ->and($dto->defaultDelayDays)->toBe(5)
        ->and($dto->defaultPaymentTermsDays)->toBe(45)
        ->and($dto->defaultAutoGenerate)->toBeFalse()
        ->and($dto->defaultAutoSend)->toBeTrue()
        ->and($dto->advanceDefaultFrequency)->toBe('weekly')
        ->and($dto->advanceDefaultGenerationDayType)->toBe('fixed_delay')
        ->and($dto->advanceDefaultGenerationDayOfWeek)->toBe(1)
        ->and($dto->advanceDefaultDelayDays)->toBe(3)
        ->and($dto->advanceDefaultPaymentTermsDays)->toBe(15)
        ->and($dto->advanceDefaultAutoGenerate)->toBeTrue()
        ->and($dto->advanceDefaultAutoSend)->toBeTrue()
        ->and($dto->standardDefaultFrequency)->toBe('bi_weekly')
        ->and($dto->standardDefaultGenerationDayType)->toBe('day_of_week')
        ->and($dto->standardDefaultGenerationDayOfWeek)->toBe(4)
        ->and($dto->standardDefaultDelayDays)->toBe(6)
        ->and($dto->standardDefaultPaymentTermsDays)->toBe(25)
        ->and($dto->standardDefaultAutoGenerate)->toBeFalse()
        ->and($dto->reminderDaysBeforeDue)->toBe(7)
        ->and($dto->reminderDaysAfterDue)->toBe(5)
        ->and($dto->reminderOverdueRepeatDays)->toBe(10)
        ->and($dto->maxOverdueReminders)->toBe(5);
});

test('billing settings dto uses defaults for missing fields', function () {
    $dto = BillingSettingsDTO::fromArray([]);

    expect($dto->defaultFrequency)->toBe('semi_monthly')
        ->and($dto->defaultGenerationDayType)->toBe('day_of_week')
        ->and($dto->defaultGenerationDayOfWeek)->toBe(2)
        ->and($dto->defaultDelayDays)->toBe(2)
        ->and($dto->defaultPaymentTermsDays)->toBe(30)
        ->and($dto->defaultAutoGenerate)->toBeTrue()
        ->and($dto->defaultAutoSend)->toBeFalse()
        ->and($dto->advanceDefaultFrequency)->toBe('semi_monthly')
        ->and($dto->advanceDefaultGenerationDayType)->toBe('day_of_week')
        ->and($dto->advanceDefaultGenerationDayOfWeek)->toBe(2)
        ->and($dto->advanceDefaultDelayDays)->toBe(2)
        ->and($dto->advanceDefaultPaymentTermsDays)->toBe(30)
        ->and($dto->advanceDefaultAutoGenerate)->toBeTrue()
        ->and($dto->advanceDefaultAutoSend)->toBeFalse()
        ->and($dto->standardDefaultFrequency)->toBe('semi_monthly')
        ->and($dto->standardDefaultGenerationDayType)->toBe('day_of_week')
        ->and($dto->standardDefaultGenerationDayOfWeek)->toBe(2)
        ->and($dto->standardDefaultDelayDays)->toBe(2)
        ->and($dto->standardDefaultPaymentTermsDays)->toBe(30)
        ->and($dto->standardDefaultAutoGenerate)->toBeTrue()
        ->and($dto->reminderDaysBeforeDue)->toBe(5)
        ->and($dto->reminderDaysAfterDue)->toBe(3)
        ->and($dto->reminderOverdueRepeatDays)->toBe(7)
        ->and($dto->maxOverdueReminders)->toBe(3);
});

test('billing settings dto round-trips via toArray', function () {
    $data = [
        'default_frequency' => 'weekly',
        'default_generation_day_type' => 'day_of_week',
        'default_generation_day_of_week' => 1,
        'default_delay_days' => 0,
        'default_payment_terms_days' => 60,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'advance_default_frequency' => 'monthly',
        'advance_default_generation_day_type' => 'fixed_delay',
        'advance_default_generation_day_of_week' => 4,
        'advance_default_delay_days' => 1,
        'advance_default_payment_terms_days' => 20,
        'advance_default_auto_generate' => false,
        'advance_default_auto_send' => true,
        'standard_default_frequency' => 'monthly',
        'standard_default_generation_day_type' => 'fixed_delay',
        'standard_default_generation_day_of_week' => 5,
        'standard_default_delay_days' => 4,
        'standard_default_payment_terms_days' => 35,
        'standard_default_auto_generate' => true,
        'reminder_days_before_due' => 3,
        'reminder_days_after_due' => 1,
        'reminder_overdue_repeat_days' => 14,
        'max_overdue_reminders' => 2,
    ];

    $dto = BillingSettingsDTO::fromArray($data);

    expect($dto->toArray())->toMatchArray($data);
});
