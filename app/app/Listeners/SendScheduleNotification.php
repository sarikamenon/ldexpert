<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Mail\ScheduleNotificationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

class SendScheduleNotification
{
    /**
     * Track email failures per schedule ID for this request
     */
    private static array $emailFailures = [];

    public function handle(ScheduleCreated|ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;

        // Eager load relationships if missing to avoid N+1 in loop/mail view
        $schedule->loadMissing(['therapist', 'student', 'service']);

        $type = $event instanceof ScheduleCreated ? 'created' : 'updated';
        $emailFailed = false;

        // Notify Therapist
        if ($schedule->therapist && $schedule->therapist->email) {
            try {
                Mail::to($schedule->therapist->email)->send(
                    new ScheduleNotificationMail($schedule, $type, isRecipientStudent: false)
                );
            } catch (TransportException $e) {
                $emailFailed = true;
                Log::error('Failed to send schedule notification email to therapist', [
                    'schedule_id' => $schedule->id,
                    'therapist_id' => $schedule->therapist->id,
                    'therapist_email' => $schedule->therapist->email,
                    'error' => $e->getMessage(),
                ]);
                // Don't rethrow - allow schedule creation to succeed even if email fails
            } catch (\Exception $e) {
                $emailFailed = true;
                Log::error('Unexpected error sending schedule notification email to therapist', [
                    'schedule_id' => $schedule->id,
                    'therapist_id' => $schedule->therapist->id,
                    'therapist_email' => $schedule->therapist->email,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
                // Don't rethrow - allow schedule creation to succeed even if email fails
            }
        }

        // Notify Student
        if ($schedule->student && $schedule->student->email) {
            try {
                Mail::to($schedule->student->email)->send(
                    new ScheduleNotificationMail($schedule, $type, isRecipientStudent: true)
                );
            } catch (TransportException $e) {
                $emailFailed = true;
                Log::error('Failed to send schedule notification email to student', [
                    'schedule_id' => $schedule->id,
                    'student_id' => $schedule->student->id,
                    'student_email' => $schedule->student->email,
                    'error' => $e->getMessage(),
                ]);
                // Don't rethrow - allow schedule creation to succeed even if email fails
            } catch (\Exception $e) {
                $emailFailed = true;
                Log::error('Unexpected error sending schedule notification email to student', [
                    'schedule_id' => $schedule->id,
                    'student_id' => $schedule->student->id,
                    'student_email' => $schedule->student->email,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
                // Don't rethrow - allow schedule creation to succeed even if email fails
            }
        }

        // Store email failure status for this schedule
        if ($emailFailed) {
            self::$emailFailures[$schedule->id] = true;
        }
    }

    /**
     * Check if email failed for a schedule
     */
    public static function hasEmailFailure(int $scheduleId): bool
    {
        return isset(self::$emailFailures[$scheduleId]);
    }

    /**
     * Clear email failure tracking (useful for testing)
     */
    public static function clearFailures(): void
    {
        self::$emailFailures = [];
    }
}
