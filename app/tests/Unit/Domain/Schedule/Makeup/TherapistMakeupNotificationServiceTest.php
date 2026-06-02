<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use App\Mail\ScheduleMakeup\TherapistAvailabilityReminderMail;
use App\Mail\ScheduleMakeup\TherapistDeclinedNotificationMail;
use App\Mail\ScheduleMakeup\TherapistMakeupScheduledMail;
use App\Mail\ScheduleMakeup\TherapistNoAvailabilityAcceptedMail;
use App\Mail\ScheduleMakeup\TherapistNonAcceptedNotificationMail;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->service = app(TherapistMakeupNotificationService::class);
});

/**
 * Build a make-up request wired to a therapist + student (with profile) and a
 * schedule whose school carries the given private-student flag.
 *
 * @return array{0: User, 1: ScheduleMakeupRequest}
 */
function notificationFixture(bool $privateSchool = false): array
{
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create();
    StudentProfile::factory()->create(['user_id' => $student->id]);

    $school = School::factory()->create(['is_private_student' => $privateSchool]);
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'school_id' => $school->id,
    ]);

    $request = ScheduleMakeupRequest::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
    ]);

    return [$therapist, $request];
}

// ─── availability reminder ──────────────────────────────────────────────────

it('sends the availability reminder to the therapist', function () {
    $therapist = User::factory()->therapist()->create();
    $event = SchoolCalendarEvent::factory()->create();

    $this->service->sendAvailabilityReminder($therapist, $event);

    Mail::assertSent(
        TherapistAvailabilityReminderMail::class,
        fn (TherapistAvailabilityReminderMail $mail): bool => $mail->hasTo($therapist->email),
    );
});

// ─── no-availability accepted (Path 2) ──────────────────────────────────────

it('sends the no-availability-accepted mail and logs it as sent', function () {
    [$therapist, $request] = notificationFixture();

    $this->service->sendNoAvailabilityAccepted($request);

    Mail::assertSent(
        TherapistNoAvailabilityAcceptedMail::class,
        fn (TherapistNoAvailabilityAcceptedMail $mail): bool => $mail->hasTo($therapist->email),
    );

    $this->assertDatabaseHas('schedule_makeup_request_email_logs', [
        'schedule_makeup_request_id' => $request->id,
        'type' => ScheduleMakeupEmailLogType::THERAPIST_NO_AVAILABILITY_ACCEPTED->value,
        'status' => ScheduleMakeupEmailLogStatus::SENT->value,
    ]);
});

// ─── declined (non-private only) ────────────────────────────────────────────

it('sends the declined notification for a non-private student', function () {
    [$therapist, $request] = notificationFixture(privateSchool: false);

    $this->service->sendDeclinedNotification($request);

    Mail::assertSent(TherapistDeclinedNotificationMail::class);

    $this->assertDatabaseHas('schedule_makeup_request_email_logs', [
        'schedule_makeup_request_id' => $request->id,
        'type' => ScheduleMakeupEmailLogType::THERAPIST_DECLINED->value,
    ]);
});

it('does not send the declined notification for a private student', function () {
    [, $request] = notificationFixture(privateSchool: true);

    $this->service->sendDeclinedNotification($request);

    Mail::assertNothingSent();

    $this->assertDatabaseMissing('schedule_makeup_request_email_logs', [
        'schedule_makeup_request_id' => $request->id,
        'type' => ScheduleMakeupEmailLogType::THERAPIST_DECLINED->value,
    ]);
});

// ─── makeup scheduled (Path 1) ──────────────────────────────────────────────

it('sends the makeup-scheduled mail with the formatted datetime', function () {
    [$therapist, $request] = notificationFixture();

    $this->service->sendMakeupScheduled($request, 'Jul 1, 2026 2:00 PM');

    Mail::assertSent(
        TherapistMakeupScheduledMail::class,
        fn (TherapistMakeupScheduledMail $mail): bool => $mail->hasTo($therapist->email),
    );

    $this->assertDatabaseHas('schedule_makeup_request_email_logs', [
        'schedule_makeup_request_id' => $request->id,
        'type' => ScheduleMakeupEmailLogType::THERAPIST_MAKEUP_SCHEDULED->value,
        'status' => ScheduleMakeupEmailLogStatus::SENT->value,
    ]);
});

// ─── non-accepted / auto-decline (non-private only) ─────────────────────────

it('sends the non-accepted notification for a non-private student', function () {
    [, $request] = notificationFixture(privateSchool: false);

    $this->service->sendNonAcceptedNotification($request);

    Mail::assertSent(TherapistNonAcceptedNotificationMail::class);

    $this->assertDatabaseHas('schedule_makeup_request_email_logs', [
        'schedule_makeup_request_id' => $request->id,
        'type' => ScheduleMakeupEmailLogType::THERAPIST_NON_ACCEPTED->value,
    ]);
});

it('does not send the non-accepted notification for a private student', function () {
    [, $request] = notificationFixture(privateSchool: true);

    $this->service->sendNonAcceptedNotification($request);

    Mail::assertNothingSent();
});
