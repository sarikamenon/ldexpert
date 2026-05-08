<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\ScheduleCreated;
use App\Events\ScheduleUpdated;
use App\Listeners\SendScheduleNotification;
use App\Mail\ScheduleNotificationMail;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendScheduleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_to_student_schedule_email_on_created(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(scheduleEmail: 'parent@example.com');

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertSent(ScheduleNotificationMail::class, function ($mail) {
            return $mail->hasTo('parent@example.com');
        });
    }

    public function test_sends_to_student_schedule_email_on_updated(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(scheduleEmail: 'parent@example.com');

        (new SendScheduleNotification())->handle(new ScheduleUpdated($schedule));

        Mail::assertSent(ScheduleNotificationMail::class, function ($mail) {
            return $mail->hasTo('parent@example.com');
        });
    }

    public function test_does_not_send_to_therapist(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(
            scheduleEmail: 'parent@example.com',
            therapistEmail: 'therapist@example.com',
        );

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertNotSent(ScheduleNotificationMail::class, function ($mail) {
            return $mail->hasTo('therapist@example.com');
        });
    }

    public function test_sends_exactly_one_email(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(scheduleEmail: 'parent@example.com');

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertSentCount(1);
    }

    public function test_skips_when_no_student_schedule_email(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(scheduleEmail: null);

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertNothingSent();
    }

    public function test_skips_past_schedules(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(
            scheduleEmail: 'parent@example.com',
            date: Carbon::yesterday(),
        );

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertNothingSent();
    }

    public function test_skips_when_service_blocks_email(): void
    {
        Mail::fake();

        $schedule = $this->makeSchedule(
            scheduleEmail: 'parent@example.com',
            sendEmail: false,
        );

        (new SendScheduleNotification())->handle(new ScheduleCreated($schedule));

        Mail::assertNothingSent();
    }

    // ---------------------------------------------------------------------------

    private function makeSchedule(
        ?string $scheduleEmail = 'parent@example.com',
        string $therapistEmail = 'therapist@example.com',
        bool $sendEmail = true,
        ?Carbon $date = null,
    ): Schedule {
        $therapist = User::factory()->create(['email' => $therapistEmail]);
        TherapistProfile::factory()->create(['user_id' => $therapist->id]);
        $therapist->load('therapistProfile');

        $student = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id'        => $student->id,
            'schedule_email' => $scheduleEmail,
        ]);
        $student->load('studentProfile');

        $service = Service::factory()->create([
            'is_direct_service' => false,
            'send_email'        => $sendEmail,
        ]);

        $scheduleDate = $date ?? Carbon::tomorrow();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'therapist_id'  => $therapist->id,
            'student_id'    => $student->id,
            'service_id'    => $service->id,
            'schedule_date' => $scheduleDate,
            'start_time'    => $scheduleDate->copy()->setTime(9, 0),
            'end_time'      => $scheduleDate->copy()->setTime(10, 0),
        ]);

        $schedule->setRelation('therapist', $therapist);
        $schedule->setRelation('student', $student);
        $schedule->setRelation('service', $service);

        return $schedule;
    }
}
