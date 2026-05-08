<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\ScheduleNotificationMail;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleNotificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_subject_uses_brand_name_and_new_schedule_label(): void
    {
        $schedule = $this->makeSchedule();

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $envelope = $mail->envelope();

        $brandName = config('brand.name');
        $this->assertStringStartsWith("{$brandName} - New Schedule:", $envelope->subject);
    }

    public function test_updated_subject_uses_schedule_update_label(): void
    {
        $schedule = $this->makeSchedule();

        $mail = new ScheduleNotificationMail($schedule, 'updated', isRecipientStudent: true);
        $envelope = $mail->envelope();

        $brandName = config('brand.name');
        $this->assertStringStartsWith("{$brandName} - Schedule Update:", $envelope->subject);
    }

    public function test_subject_contains_formatted_date(): void
    {
        $schedule = $this->makeSchedule();

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $envelope = $mail->envelope();

        $this->assertMatchesRegularExpression('/\w+ \d+, \d{4}/', $envelope->subject);
    }

    public function test_content_passes_therapist_email_to_view(): void
    {
        $schedule = $this->makeSchedule(therapistEmail: 'therapist@example.com');

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $content = $mail->content();

        $this->assertSame('therapist@example.com', $content->with['therapistEmail']);
    }

    public function test_content_passes_therapist_phone_to_view(): void
    {
        $schedule = $this->makeSchedule(therapistPhone: '555-1234');

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $content = $mail->content();

        $this->assertSame('555-1234', $content->with['therapistPhone']);
    }

    public function test_content_passes_empty_strings_when_therapist_is_null(): void
    {
        $schedule = $this->makeSchedule();
        $schedule->setRelation('therapist', null);

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $content = $mail->content();

        $this->assertSame('', $content->with['therapistEmail']);
        $this->assertSame('', $content->with['therapistPhone']);
    }

    public function test_content_uses_schedule_notification_view(): void
    {
        $schedule = $this->makeSchedule();

        $mail = new ScheduleNotificationMail($schedule, 'created', isRecipientStudent: true);
        $content = $mail->content();

        $this->assertSame('emails.schedule-notification', $content->view);
    }

    // ---------------------------------------------------------------------------

    private function makeSchedule(
        string $therapistEmail = 'th@example.com',
        string $therapistPhone = '123-456-7890',
    ): Schedule {
        $therapist = User::factory()->create(['email' => $therapistEmail]);
        TherapistProfile::factory()->create([
            'user_id' => $therapist->id,
            'phone'   => $therapistPhone,
        ]);
        $therapist->load('therapistProfile');

        $student = User::factory()->create();
        $service = Service::factory()->create(['send_email' => true]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'therapist_id'  => $therapist->id,
            'student_id'    => $student->id,
            'service_id'    => $service->id,
            'schedule_date' => Carbon::tomorrow(),
            'start_time'    => Carbon::tomorrow()->setTime(9, 0),
            'end_time'      => Carbon::tomorrow()->setTime(10, 0),
        ]);

        $schedule->setRelation('therapist', $therapist);
        $schedule->setRelation('student', $student);
        $schedule->setRelation('service', $service);

        return $schedule;
    }
}
