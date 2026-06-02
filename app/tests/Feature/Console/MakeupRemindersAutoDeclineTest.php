<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\ScheduleMakeupRequest;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ─── Happy path ─────────────────────────────────────────────────────────────

it('auto-declines sent rows whose response_date is strictly before today', function () {
    Mail::fake();
    $student = \App\Models\User::factory()->student()->create();

    $overdue = ScheduleMakeupRequest::factory()->sent()->create([
        'student_id' => $student->id,
        'responded_at' => null,
        'response_date' => now()->subDay()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline')->assertExitCode(0);

    $overdue->refresh();
    expect($overdue->status)->toBe(ScheduleMakeupRequestStatus::DECLINED)
        ->and($overdue->responded_by_type)->toBe(ScheduleMakeupRespondedByType::SYSTEM)
        ->and($overdue->response_source)->toBe(ScheduleMakeupResponseSource::AUTO_DECLINED)
        ->and($overdue->responded_by_user_id)->toBeNull()
        ->and($overdue->responded_at)->not->toBeNull();
});

it('command output includes the count of auto-declined rows', function () {
    Mail::fake();
    $student = \App\Models\User::factory()->student()->create();

    ScheduleMakeupRequest::factory()->sent()->create([
        'student_id' => $student->id,
        'responded_at' => null,
        'response_date' => now()->subDay()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline')
        ->expectsOutputToContain('Auto-declined 1')
        ->assertExitCode(0);
});

// ─── Boundary: response_date == today should NOT be declined ─────────────────

it('does not decline rows whose response_date is today', function () {
    $today = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline');

    expect($today->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
});

// ─── Already-responded rows are untouched ────────────────────────────────────

it('does not touch sent rows that already have a responded_at', function () {
    $responded = ScheduleMakeupRequest::factory()->requested()->create([
        'response_date' => now()->subDay()->toDateString(),
        'responded_at' => now()->subHours(2),
    ]);

    $this->artisan('makeup-reminders:auto-decline');

    expect($responded->fresh()->status)->toBe(ScheduleMakeupRequestStatus::REQUESTED);
});

// ─── Pending rows are not affected ───────────────────────────────────────────

it('does not touch pending rows even if response_date is past', function () {
    $pending = ScheduleMakeupRequest::factory()->pending()->create([
        'responded_at' => null,
        'response_date' => now()->subDay()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline');

    expect($pending->fresh()->status)->toBe(ScheduleMakeupRequestStatus::PENDING);
});

// ─── Multiple rows declined in one run ───────────────────────────────────────

it('bulk-declines multiple overdue rows in a single run', function () {
    Mail::fake();

    $student = \App\Models\User::factory()->student()->create();

    $rows = ScheduleMakeupRequest::factory()->count(3)->sent()->create([
        'student_id' => $student->id,
        'responded_at' => null,
        'response_date' => now()->subDays(2)->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline')
        ->expectsOutputToContain('Auto-declined 3');

    foreach ($rows as $row) {
        expect($row->fresh()->status)->toBe(ScheduleMakeupRequestStatus::DECLINED);
    }
});

// ─── Therapist notification on auto-decline (non-private students) ────────────

it('sends therapist non-accepted email for each auto-declined row', function () {
    Mail::fake();

    $therapist = \App\Models\User::factory()->therapist()->create();
    $student = \App\Models\User::factory()->student()->create();

    // User::factory()->student() auto-creates a StudentProfile — update it in place
    StudentProfile::where('user_id', $student->id)->update([
        'first_name' => 'Jane',
        'last_name' => 'Student',
    ]);

    // School with is_private_student = false so the notification is not suppressed
    $school = \App\Models\School::factory()->create(['is_private_student' => false]);

    $schedule = \App\Models\Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
    ]);

    ScheduleMakeupRequest::factory()->sent()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'responded_at' => null,
        'response_date' => now()->subDay()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:auto-decline');

    Mail::assertSent(\App\Mail\ScheduleMakeup\TherapistNonAcceptedNotificationMail::class);
});
