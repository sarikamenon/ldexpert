<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingSettingsService;
use App\DTOs\BillingSettingsDTO;
use App\Models\BillingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing settings service returns settings singleton', function () {
    $service = new BillingSettingsService;
    $settings = $service->getSettings();

    expect($settings)->toBeInstanceOf(BillingSetting::class)
        ->and($settings->default_frequency->value)->toBe('semi_monthly');
});

test('billing settings service updates settings', function () {
    $service = new BillingSettingsService;

    $dto = BillingSettingsDTO::fromArray([
        'default_frequency' => 'monthly',
        'default_generation_day_type' => 'fixed_delay',
        'default_generation_day_of_week' => 4,
        'default_min_grace_days' => 5,
        'default_payment_terms_days' => 45,
        'default_auto_generate' => false,
        'default_auto_send' => true,
        'reminder_days_before_due' => 10,
        'reminder_days_after_due' => 7,
        'reminder_overdue_repeat_days' => 14,
        'max_overdue_reminders' => 5,
    ]);

    $updated = $service->updateSettings($dto);

    expect($updated->default_frequency->value)->toBe('monthly')
        ->and($updated->default_min_grace_days)->toBe(5)
        ->and($updated->default_payment_terms_days)->toBe(45)
        ->and($updated->default_auto_generate)->toBeFalse()
        ->and($updated->default_auto_send)->toBeTrue()
        ->and($updated->reminder_days_before_due)->toBe(10)
        ->and($updated->max_overdue_reminders)->toBe(5);
});

test('billing settings service updates persist in database', function () {
    $service = new BillingSettingsService;

    $dto = BillingSettingsDTO::fromArray([
        'default_frequency' => 'weekly',
        'default_generation_day_type' => 'day_of_week',
        'default_generation_day_of_week' => 1,
        'default_min_grace_days' => 0,
        'default_payment_terms_days' => 15,
        'default_auto_generate' => true,
        'default_auto_send' => false,
        'reminder_days_before_due' => 3,
        'reminder_days_after_due' => 1,
        'reminder_overdue_repeat_days' => 5,
        'max_overdue_reminders' => 1,
    ]);

    $service->updateSettings($dto);

    $fresh = $service->getSettings();
    expect($fresh->default_frequency->value)->toBe('weekly')
        ->and($fresh->default_payment_terms_days)->toBe(15)
        ->and($fresh->max_overdue_reminders)->toBe(1);
});
