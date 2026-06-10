<?php

declare(strict_types=1);

use App\Domain\Billing\Services\BillingScheduleService;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Models\BillingSchedule;
use App\Models\BillingSetting;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BillingScheduleService::class);

    // Distinct defaults so the advance/standard fallback branches are testable.
    BillingSetting::getSettings()->update([
        'advance_default_payment_terms_days' => 14,
        'standard_default_payment_terms_days' => 45,
    ]);
});

test('prefers the school_invoice schedule payment terms over the settings default', function () {
    $school = School::factory()->create(['is_private_student' => false]);
    BillingSchedule::factory()->forSchool($school)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
        'payment_terms_days' => 7,
    ]);

    expect($this->service->resolveSchoolPaymentTermsDays($school))->toBe(7);
});

test('falls back to the advance default for a private-student school with no schedule', function () {
    $school = School::factory()->create(['is_private_student' => true]);

    expect($this->service->resolveSchoolPaymentTermsDays($school))->toBe(14);
});

test('falls back to the standard default for a non-private school with no schedule', function () {
    $school = School::factory()->create(['is_private_student' => false]);

    expect($this->service->resolveSchoolPaymentTermsDays($school))->toBe(45);
});

test('batch map resolves schedule terms, advance fallback, and standard fallback together', function () {
    $withSchedule = School::factory()->create(['is_private_student' => false]);
    BillingSchedule::factory()->forSchool($withSchedule)->create([
        'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        'billing_mode' => BillingMode::STANDARD->value,
        'payment_terms_days' => 7,
    ]);

    $privateNoSchedule = School::factory()->create(['is_private_student' => true]);
    $standardNoSchedule = School::factory()->create(['is_private_student' => false]);

    $schools = collect([$withSchedule, $privateNoSchedule, $standardNoSchedule]);

    $map = $this->service->resolveSchoolPaymentTermsDaysMap($schools);

    expect($map)
        ->toBe([
            $withSchedule->id => 7,
            $privateNoSchedule->id => 14,
            $standardNoSchedule->id => 45,
        ]);
});

test('batch map returns an empty array for no schools', function () {
    expect($this->service->resolveSchoolPaymentTermsDaysMap(collect()))->toBe([]);
});
