<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingReminderType;
use App\Enums\BillingScheduleRunStatus;
use App\Enums\BillingScheduleType;

test('billing frequency has expected cases and labels', function () {
    expect(BillingFrequency::cases())->toHaveCount(4)
        ->and(BillingFrequency::WEEKLY->value)->toBe('weekly')
        ->and(BillingFrequency::BI_WEEKLY->value)->toBe('bi_weekly')
        ->and(BillingFrequency::SEMI_MONTHLY->value)->toBe('semi_monthly')
        ->and(BillingFrequency::MONTHLY->value)->toBe('monthly')
        ->and(BillingFrequency::WEEKLY->label())->toBe('Weekly')
        ->and(BillingFrequency::BI_WEEKLY->label())->toBe('Bi-Weekly')
        ->and(BillingFrequency::SEMI_MONTHLY->label())->toBe('Semi-Monthly')
        ->and(BillingFrequency::MONTHLY->label())->toBe('Monthly');
});

test('billing frequency values returns string array', function () {
    $values = BillingFrequency::values();

    expect($values)->toContain('weekly', 'bi_weekly', 'semi_monthly', 'monthly')
        ->and($values)->toHaveCount(4);
});

test('billing mode has expected cases and labels', function () {
    expect(BillingMode::cases())->toHaveCount(2)
        ->and(BillingMode::STANDARD->value)->toBe('standard')
        ->and(BillingMode::ADVANCE->value)->toBe('advance')
        ->and(BillingMode::STANDARD->label())->toBe('Standard')
        ->and(BillingMode::ADVANCE->label())->toBe('Advance');
});

test('billing reminder type has expected cases and labels', function () {
    expect(BillingReminderType::cases())->toHaveCount(3)
        ->and(BillingReminderType::UPCOMING_DUE->value)->toBe('upcoming_due')
        ->and(BillingReminderType::OVERDUE->value)->toBe('overdue')
        ->and(BillingReminderType::OVERDUE_FOLLOWUP->value)->toBe('overdue_followup')
        ->and(BillingReminderType::UPCOMING_DUE->label())->toBe('Upcoming Due')
        ->and(BillingReminderType::OVERDUE->label())->toBe('Overdue')
        ->and(BillingReminderType::OVERDUE_FOLLOWUP->label())->toBe('Overdue Follow-up');
});

test('billing schedule run status has expected cases and labels', function () {
    expect(BillingScheduleRunStatus::cases())->toHaveCount(3)
        ->and(BillingScheduleRunStatus::SUCCESS->value)->toBe('success')
        ->and(BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value)->toBe('skipped_no_sessions')
        ->and(BillingScheduleRunStatus::FAILED->value)->toBe('failed')
        ->and(BillingScheduleRunStatus::FAILED->label())->toBe('Failed');
});

test('billing schedule type has expected cases and labels', function () {
    expect(BillingScheduleType::cases())->toHaveCount(3)
        ->and(BillingScheduleType::SCHOOL_INVOICE->value)->toBe('school_invoice')
        ->and(BillingScheduleType::PRIVATE_STUDENT_INVOICE->value)->toBe('private_student_invoice')
        ->and(BillingScheduleType::THERAPIST_BILL->value)->toBe('therapist_bill')
        ->and(BillingScheduleType::SCHOOL_INVOICE->label())->toBe('School Invoice')
        ->and(BillingScheduleType::PRIVATE_STUDENT_INVOICE->label())->toBe('Private Student Invoice')
        ->and(BillingScheduleType::THERAPIST_BILL->label())->toBe('Therapist Bill');
});
