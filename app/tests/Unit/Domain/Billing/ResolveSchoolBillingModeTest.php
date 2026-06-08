<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingScheduleService;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Models\BillingSchedule;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BillingScheduleService::class);
});

test('resolves advance when the school has an advance school_invoice schedule', function () {
    $school = School::factory()->create(['is_private_student' => false]);
    BillingSchedule::factory()->forSchool($school)->advance()->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
    ]);

    expect($this->service->resolveSchoolBillingMode($school))->toBe(BillingMode::ADVANCE);
});

test('resolves standard when the school has a standard school_invoice schedule', function () {
    $school = School::factory()->create(['is_private_student' => true]);
    BillingSchedule::factory()->forSchool($school)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
    ]);

    expect($this->service->resolveSchoolBillingMode($school))->toBe(BillingMode::STANDARD);
});

test('falls back to advance for a private-student school with no schedule (pre-Phase-4)', function () {
    $school = School::factory()->create(['is_private_student' => true]);

    expect($this->service->resolveSchoolBillingMode($school))->toBe(BillingMode::ADVANCE);
});

test('falls back to standard for a non-private school with no schedule (pre-Phase-4)', function () {
    $school = School::factory()->create(['is_private_student' => false]);

    expect($this->service->resolveSchoolBillingMode($school))->toBe(BillingMode::STANDARD);
});

test('prefers the stored schedule mode over the is_private_student flag', function () {
    // Private flag says advance, but the stored schedule says standard — schedule wins.
    $school = School::factory()->create(['is_private_student' => true]);
    BillingSchedule::factory()->forSchool($school)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
    ]);

    expect($this->service->resolveSchoolBillingMode($school))->toBe(BillingMode::STANDARD);
});
