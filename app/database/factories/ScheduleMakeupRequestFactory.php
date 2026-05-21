<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduleMakeupRequest>
 */
class ScheduleMakeupRequestFactory extends Factory
{
    protected $model = ScheduleMakeupRequest::class;

    public function definition(): array
    {
        $eventDate = now()->addDays(7)->startOfDay();

        return [
            'school_calendar_event_id' => SchoolCalendarEvent::factory(),
            'schedule_id' => Schedule::factory(),
            'student_id' => User::factory(),
            'therapist_id' => User::factory(),
            'event_date' => $eventDate->toDateString(),
            'reminder_date' => $eventDate->copy()->subDays(7)->toDateString(),
            'response_date' => $eventDate->copy()->subDays(5)->toDateString(),
            'deadline_date' => $eventDate->copy()->subDays(3)->toDateString(),
            'status' => ScheduleMakeupRequestStatus::PENDING->value,
            'batch_number' => Str::random(32),
            'reminder_sent_at' => null,
            'response_token' => Str::random(64),
            'responded_at' => null,
            'responded_by_type' => null,
            'responded_by_user_id' => null,
            'response_source' => null,
            'decline_reason' => null,
            'makeup_schedule_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::PENDING->value,
            'reminder_sent_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::SENT->value,
            'reminder_sent_at' => now(),
        ]);
    }

    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::REQUESTED->value,
            'reminder_sent_at' => now()->subDay(),
            'responded_at' => now(),
            'responded_by_type' => ScheduleMakeupRespondedByType::PARENT->value,
            'response_source' => ScheduleMakeupResponseSource::EMAIL_LINK->value,
            'responded_by_user_id' => User::factory(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::DECLINED->value,
            'reminder_sent_at' => now()->subDay(),
            'responded_at' => now(),
            'responded_by_type' => ScheduleMakeupRespondedByType::PARENT->value,
            'response_source' => ScheduleMakeupResponseSource::EMAIL_LINK->value,
            'responded_by_user_id' => User::factory(),
        ]);
    }

    public function autoDeclined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::DECLINED->value,
            'reminder_sent_at' => now()->subDays(7),
            'responded_at' => now(),
            'responded_by_type' => ScheduleMakeupRespondedByType::SYSTEM->value,
            'response_source' => ScheduleMakeupResponseSource::AUTO_DECLINED->value,
            'responded_by_user_id' => null,
        ]);
    }

    public function scheduled(Schedule $makeup): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::SCHEDULED->value,
            'reminder_sent_at' => now()->subDays(2),
            'responded_at' => now()->subDay(),
            'responded_by_type' => ScheduleMakeupRespondedByType::PARENT->value,
            'response_source' => ScheduleMakeupResponseSource::EMAIL_LINK->value,
            'makeup_schedule_id' => $makeup->id,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupRequestStatus::FAILED->value,
        ]);
    }
}
