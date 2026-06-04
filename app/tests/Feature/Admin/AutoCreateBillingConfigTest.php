<?php

declare(strict_types=1);

use App\Domain\School\Services\SchoolService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\CreateTherapistDTO;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Models\BillingSchedule;
use App\Models\Position;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function makeTherapistDto(int $managerId): CreateTherapistDTO
{
    $position = Position::factory()->create(['name' => 'SLP']);

    return new CreateTherapistDTO(
        employeeType: 'W2',
        title: 'Dr.',
        firstName: 'John',
        lastName: 'Doe',
        personalEmail: 'john'.uniqid().'@example.com',
        phone: '123-456-7890',
        ldEmail: 'john'.uniqid().'@ldexpert.com',
        llcName: null,
        address: null,
        comments: null,
        positionId: $position->id,
        state: 'CA',
        timezone: 'America/Los_Angeles',
        managerId: $managerId,
        maxWeeklyHours: 40,
        hourlyRate: 75.00,
        dob: null,
        defaultMeetingLocation: null,
        password: 'SecurePass123!'
    );
}

test('creating a therapist seeds a therapist_bill billing schedule', function () {
    $manager = User::factory()->admin()->create();

    $profile = app(TherapistService::class)->create(makeTherapistDto($manager->id));

    $schedule = BillingSchedule::query()
        ->where('schedulable_type', User::class)
        ->where('schedulable_id', $profile->user_id)
        ->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->schedule_type)->toBe(BillingScheduleType::THERAPIST_BILL)
        ->and($schedule->billing_mode)->toBe(BillingMode::STANDARD)
        ->and($schedule->billing_start_date)->not->toBeNull();
});

test('creating a private-student school seeds an advance school_invoice schedule starting next month', function () {
    $manager = User::factory()->admin()->create();

    $dto = CreateSchoolDTO::fromArray([
        'full_name' => 'Private Family',
        'display_name' => 'Private Family',
        'address' => '1 Main',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => $manager->id,
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-555-5555',
        'contact_email' => 'jane@example.com',
        'invoice_email' => 'billing@example.com',
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'non_billable_scheduling' => false,
    ]);

    $school = app(SchoolService::class)->createSchool($dto);

    $schedule = BillingSchedule::query()
        ->where('schedulable_type', School::class)
        ->where('schedulable_id', $school->id)
        ->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->schedule_type)->toBe(BillingScheduleType::SCHOOL_INVOICE)
        ->and($schedule->billing_mode)->toBe(BillingMode::ADVANCE)
        ->and($schedule->billing_start_date->toDateString())->toBe(now()->copy()->addMonthNoOverflow()->startOfMonth()->toDateString());
});

test('creating a non-private school seeds a standard school_invoice schedule starting this month', function () {
    $manager = User::factory()->admin()->create();

    $dto = CreateSchoolDTO::fromArray([
        'full_name' => 'Public School',
        'display_name' => 'Public School',
        'address' => '2 Main',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => $manager->id,
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-555-5555',
        'contact_email' => 'jane@example.com',
        'invoice_email' => 'billing@example.com',
        'school_type' => 'Virtual',
        'is_private_student' => false,
        'non_billable_scheduling' => false,
    ]);

    $school = app(SchoolService::class)->createSchool($dto);

    $schedule = BillingSchedule::query()
        ->where('schedulable_type', School::class)
        ->where('schedulable_id', $school->id)
        ->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->billing_mode)->toBe(BillingMode::STANDARD)
        ->and($schedule->billing_start_date->toDateString())->toBe(now()->copy()->startOfMonth()->toDateString());
});
