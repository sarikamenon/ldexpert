<?php

declare(strict_types=1);

use App\DTOs\BillingSettingsDTO;

test('billing settings dto creates from array with all fields', function () {
    $data = [
        'default_frequency' => 'monthly',
        'default_generation_day_type' => 'fixed_delay',
        'default_generation_day_of_week' => 3,
        'default_min_grace_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => false,
        'default_auto_send' => true,
        'reminder_days_before_due' => 7,
        'reminder_days_after_due' => 5,
        'reminder_overdue_repeat_days' => 10,
        'max_overdue_reminders' => 5,
    ];

    $dto = BillingSettingsDTO::fromArray($data);

    expect($dto->defaultFrequency)->toBe('monthly')
        ->and($dto->defaultGenerationDayType)->toBe('fixed_delay')
        ->and($dto->defaultGenerationDayOfWeek)->toBe(3)
        ->and($dto->defaultMinGraceDays)->toBe(5)
        ->and($dto->defaultPaymentTermsDays)->toBe(45)
        ->and($dto->defaultAutoGenerate)->toBeFalse()
        ->and($dto->defaultAutoSend)->toBeTrue()
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
        ->and($dto->defaultMinGraceDays)->toBe(2)
        ->and($dto->defaultPaymentTermsDays)->toBe(30)
        ->and($dto->defaultAutoGenerate)->toBeTrue()
        ->and($dto->defaultAutoSend)->toBeFalse()
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
        'default_min_grace_days' => 0,
        'default_payment_terms_days' => 60,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'reminder_days_before_due' => 3,
        'reminder_days_after_due' => 1,
        'reminder_overdue_repeat_days' => 14,
        'max_overdue_reminders' => 2,
    ];

    $dto = BillingSettingsDTO::fromArray($data);

    expect($dto->toArray())->toMatchArray($data);
});
