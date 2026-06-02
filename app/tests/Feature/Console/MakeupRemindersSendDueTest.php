<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ServiceFrequency;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\ScheduleMakeupRequestEmailLog;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Create a pending batch that is due for sending today:
 * - schedule linked to an SSA with a weekly frequency
 * - student has parent_guardian_email = 'parent@example.com' on their profile
 * - reminder_date in the past (due now)
 *
 * The schedule, SSA, and makeup request all share the same student so the
 * sender's recipient-resolution (via $request->student_id → StudentProfile)
 * finds the exact profile we created here.
 *
 * @return array{0: ScheduleMakeupRequest, 1: User}
 */
function sendDueFixture(): array
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();

    // User::factory()->student() auto-creates a StudentProfile — update it in place
    StudentProfile::where('user_id', $student->id)->update([
        'parent_guardian_email' => 'parent@example.com',
        'parent_guardian_name' => 'Jane Parent',
        'schedule_email' => null,
    ]);

    // SSA must reference the same student so the schedule inherits it correctly
    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'frequency' => ServiceFrequency::WEEKLY,
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
    ]);

    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'reminder_date' => now()->subDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    return [$request, $therapist];
}

// ─── Happy path ─────────────────────────────────────────────────────────────

it('flips due pending rows to sent after email dispatch', function () {
    Mail::fake();
    [$request] = sendDueFixture();

    $this->artisan('makeup-reminders:send-due')->assertExitCode(0);

    expect($request->fresh()->status)->toBe(ScheduleMakeupRequestStatus::SENT);
});

it('writes a sent email log row after successful dispatch', function () {
    Mail::fake();
    [$request] = sendDueFixture();

    $this->artisan('makeup-reminders:send-due');

    $log = ScheduleMakeupRequestEmailLog::where('schedule_makeup_request_id', $request->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->status->value)->toBe('sent')
        ->and($log->recipient_email)->toBe('parent@example.com');
});

it('dispatches the mailable to the parent email address', function () {
    Mail::fake();
    [$request] = sendDueFixture();

    $this->artisan('makeup-reminders:send-due');

    Mail::assertSentCount(1);
    Mail::assertSent(\App\Mail\ScheduleMakeupReminderMail::class, function ($mail) {
        return collect($mail->to)->pluck('address')->contains('parent@example.com');
    });
});

it('sets reminder_sent_at timestamp on the row', function () {
    Mail::fake(); // must be before fixture so the fake is active when artisan runs
    [$request] = sendDueFixture();

    $this->artisan('makeup-reminders:send-due');

    expect($request->fresh()->reminder_sent_at)->not->toBeNull();
});

it('command output contains sent batch count', function () {
    Mail::fake();
    sendDueFixture();

    $this->artisan('makeup-reminders:send-due')
        ->expectsOutputToContain('1 sent')
        ->assertExitCode(0);
});

// ─── Skip when no parent email ────────────────────────────────────────────────

it('skips batches with no parent email and leaves status as pending', function () {
    Mail::fake();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    // Null out both email fields so the sender skips this batch
    StudentProfile::where('user_id', $student->id)->update([
        'parent_guardian_email' => null,
        'schedule_email' => null,
    ]);

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'frequency' => ServiceFrequency::WEEKLY,
    ]);
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
    ]);

    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'reminder_date' => now()->subDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:send-due');

    expect($request->fresh()->status)->toBe(ScheduleMakeupRequestStatus::PENDING);
    Mail::assertNothingSent();
});

// ─── Not yet due ─────────────────────────────────────────────────────────────

it('does not send reminders whose reminder_date is in the future', function () {
    Mail::fake();

    $request = ScheduleMakeupRequest::factory()->pending()->create([
        'reminder_date' => now()->addDays(3)->toDateString(),
        'response_date' => now()->addDays(7)->toDateString(),
        'event_date' => now()->addDays(10)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:send-due');

    expect($request->fresh()->status)->toBe(ScheduleMakeupRequestStatus::PENDING);
    Mail::assertNothingSent();
});

// ─── Mail failure flips to failed ────────────────────────────────────────────

it('flips batch to failed and writes a failed email log when send throws', function () {
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP error'));

    [$request] = sendDueFixture();

    $this->artisan('makeup-reminders:send-due')->assertExitCode(1);

    expect($request->fresh()->status)->toBe(ScheduleMakeupRequestStatus::FAILED);

    $log = ScheduleMakeupRequestEmailLog::where('schedule_makeup_request_id', $request->id)->first();
    expect($log->status->value)->toBe('failed')
        ->and($log->error_message)->toContain('SMTP error');
});

// ─── schedule_email takes priority over parent_guardian_email ────────────────

it('uses schedule_email when present instead of parent_guardian_email', function () {
    Mail::fake();

    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update([
        'parent_guardian_email' => 'parent@example.com',
        'schedule_email' => 'schedule@example.com',
    ]);

    $ssa = ServiceSupportAgreement::factory()->active()->create([
        'student_id' => $student->id,
        'frequency' => ServiceFrequency::WEEKLY,
    ]);
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
    ]);

    ScheduleMakeupRequest::factory()->pending()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'reminder_date' => now()->subDay()->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->artisan('makeup-reminders:send-due');

    Mail::assertSent(\App\Mail\ScheduleMakeupReminderMail::class, function ($mail) {
        return collect($mail->to)->pluck('address')->contains('schedule@example.com');
    });
});
