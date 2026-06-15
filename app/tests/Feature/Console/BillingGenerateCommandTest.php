<?php

declare(strict_types=1);

use App\Enums\SessionLogStatus;
use App\Models\BillingSchedule;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing generate command processes all due schedules', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    BillingSchedule::factory()->forSchool($school)->due()->create();

    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 200.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(3),
    ]);

    $this->artisan('billing:generate')
        ->assertExitCode(0)
        ->expectsOutputToContain('Done:');
});

test('billing generate command with type filter', function () {
    User::factory()->admin()->create();

    $this->artisan('billing:generate', ['--type' => 'school_invoice'])
        ->assertExitCode(0)
        ->expectsOutputToContain('No due schedules found.');
});

test('billing generate command with schedule id', function () {
    User::factory()->admin()->create();
    $school = School::factory()->create();
    $schedule = BillingSchedule::factory()->forSchool($school)->create();

    $this->artisan('billing:generate', ['--schedule' => $schedule->id])
        ->assertExitCode(0);
});

test('billing generate command with invalid schedule id returns failure', function () {
    $this->artisan('billing:generate', ['--schedule' => 99999])
        ->assertExitCode(1)
        ->expectsOutputToContain('not found');
});

test('billing generate dry run does not create invoices', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    BillingSchedule::factory()->forSchool($school)->due()->create();

    SessionLog::factory()->create([
        'school_id' => $school->id,
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'status' => SessionLogStatus::APPROVED->value,
        'is_billable_school' => true,
        'school_invoice_amount' => 200.00,
        'invoice_id' => null,
        'session_date' => now()->subDays(3),
    ]);

    $this->artisan('billing:generate', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN');

    $this->assertDatabaseMissing('invoices', [
        'school_id' => $school->id,
    ]);
});

test('billing generate shows message when no due schedules', function () {
    User::factory()->admin()->create();

    $this->artisan('billing:generate')
        ->assertExitCode(0)
        ->expectsOutputToContain('No due schedules found.');
});
