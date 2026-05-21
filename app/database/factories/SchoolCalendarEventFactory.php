<?php

namespace Database\Factories;

use App\Enums\SchoolCalendarEventType;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolCalendarEvent>
 */
class SchoolCalendarEventFactory extends Factory
{
    protected $model = SchoolCalendarEvent::class;

    public function definition(): array
    {
        $start = now()->addDays(14);
        $startDate = $start->toDateString();

        return [
            'school_id' => School::factory(),
            'title' => $this->faker->sentence(3),
            'event_type' => SchoolCalendarEventType::HOLIDAY->value,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'reminder_date' => $start->copy()->subDays(7)->toDateString(),
            'response_date' => $start->copy()->subDays(5)->toDateString(),
            'deadline_date' => $start->copy()->subDays(3)->toDateString(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function holiday(): self
    {
        return $this->state([
            'event_type' => SchoolCalendarEventType::HOLIDAY->value,
        ]);
    }

    public function nonHoliday(): self
    {
        return $this->state([
            'event_type' => SchoolCalendarEventType::NON_HOLIDAY->value,
        ]);
    }
}
