<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use App\Mail\ScheduleMakeup\TherapistAvailabilityReminderMail;
use App\Mail\ScheduleMakeup\TherapistDeclinedNotificationMail;
use App\Mail\ScheduleMakeup\TherapistMakeupScheduledMail;
use App\Mail\ScheduleMakeup\TherapistNoAvailabilityAcceptedMail;
use App\Mail\ScheduleMakeup\TherapistNonAcceptedNotificationMail;
use App\Models\ScheduleMakeupRequest;
use App\Models\ScheduleMakeupRequestEmailLog;
use App\Models\SchoolCalendarEvent;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends therapist-facing notification emails for make-up request lifecycle events.
 * All sends are side-effects — failures are logged and swallowed so the primary
 * business action is never blocked.
 */
final class TherapistMakeupNotificationService
{
    public function __construct() {}

    /**
     * Email #1: Therapist has not added availability for an upcoming closure.
     */
    public function sendAvailabilityReminder(User $therapist, SchoolCalendarEvent $event): void
    {
        $this->safeSend(
            new TherapistAvailabilityReminderMail($therapist, $event),
            $therapist->email,
            'therapist_availability_reminder',
        );
    }

    /**
     * Email #2: Parent accepted but therapist has no availability (Path 2).
     */
    public function sendNoAvailabilityAccepted(ScheduleMakeupRequest $request): void
    {
        /** @var User $therapist */
        $therapist = $request->therapist;
        $studentName = $this->studentDisplayName($request);

        $mailable = new TherapistNoAvailabilityAcceptedMail($therapist, $studentName);

        $this->safeSendWithLog(
            $mailable,
            $therapist,
            $request,
            ScheduleMakeupEmailLogType::THERAPIST_NO_AVAILABILITY_ACCEPTED,
            "Schedule Make-Up Session for {$studentName}",
        );
    }

    /**
     * Email #3: Parent declined — only for non-private students.
     */
    public function sendDeclinedNotification(ScheduleMakeupRequest $request): void
    {
        if ($this->isPrivateStudent($request)) {
            return;
        }

        /** @var User $therapist */
        $therapist = $request->therapist;
        $studentName = $this->studentDisplayName($request);

        $mailable = new TherapistDeclinedNotificationMail($therapist, $studentName);

        $this->safeSendWithLog(
            $mailable,
            $therapist,
            $request,
            ScheduleMakeupEmailLogType::THERAPIST_DECLINED,
            "Student {$studentName} | Enter Declined Make-Up Session in RSM",
        );
    }

    /**
     * Email #4: Make-up session scheduled via availability picker.
     */
    public function sendMakeupScheduled(ScheduleMakeupRequest $request, string $scheduledDateTime): void
    {
        /** @var User $therapist */
        $therapist = $request->therapist;
        $studentName = $this->studentDisplayName($request);

        $mailable = new TherapistMakeupScheduledMail($therapist, $studentName, $scheduledDateTime);

        $this->safeSendWithLog(
            $mailable,
            $therapist,
            $request,
            ScheduleMakeupEmailLogType::THERAPIST_MAKEUP_SCHEDULED,
            "Student {$studentName} | Make-Up Session Scheduled",
        );
    }

    /**
     * Email #5: No response before deadline (auto-declined) — only for non-private students.
     */
    public function sendNonAcceptedNotification(ScheduleMakeupRequest $request): void
    {
        if ($this->isPrivateStudent($request)) {
            return;
        }

        /** @var User $therapist */
        $therapist = $request->therapist;
        $studentName = $this->studentDisplayName($request);

        $mailable = new TherapistNonAcceptedNotificationMail($therapist, $studentName);

        $this->safeSendWithLog(
            $mailable,
            $therapist,
            $request,
            ScheduleMakeupEmailLogType::THERAPIST_NON_ACCEPTED,
            "Student {$studentName} | Enter Non-Accepted Make-Up in RSM",
        );
    }

    private function studentDisplayName(ScheduleMakeupRequest $request): string
    {
        /** @var User $student */
        $student = $request->student;
        /** @var StudentProfile $profile */
        $profile = $student->studentProfile;

        return $profile->first_initial_last_name;
    }

    private function isPrivateStudent(ScheduleMakeupRequest $request): bool
    {
        return (bool) $request->schedule?->school?->is_private_student;
    }

    /**
     * Fire-and-forget send — log failure, never throw.
     */
    private function safeSend(Mailable $mailable, string $to, string $context): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $e) {
            Log::error("Therapist makeup notification ({$context}) failed", [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function safeSendWithLog(
        Mailable $mailable,
        User $therapist,
        ScheduleMakeupRequest $request,
        ScheduleMakeupEmailLogType $type,
        string $subject,
    ): void {
        $log = ScheduleMakeupRequestEmailLog::query()->create([
            'schedule_makeup_request_id' => $request->id,
            'type' => $type->value,
            'recipient_email' => $therapist->email,
            'recipient_name' => $therapist->name,
            'from_email' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'subject' => $subject,
            'status' => ScheduleMakeupEmailLogStatus::QUEUED->value,
        ]);

        try {
            Mail::to($therapist->email)->send($mailable);
            $log->fill([
                'status' => ScheduleMakeupEmailLogStatus::SENT->value,
                'sent_at' => Carbon::now()->toDateTimeString(),
            ])->save();
        } catch (Throwable $e) {
            $log->fill([
                'status' => ScheduleMakeupEmailLogStatus::FAILED->value,
                'failed_at' => Carbon::now()->toDateTimeString(),
                'error_message' => $e->getMessage(),
            ])->save();
            Log::error("Therapist makeup notification ({$type->value}) failed", [
                'request_id' => $request->id,
                'therapist_id' => $therapist->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
