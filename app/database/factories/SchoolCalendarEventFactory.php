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
        $startDate = now()->addDays(2)->toDateString();

        return [
            'school_id' => School::factory(),
            'title' => $this->faker->sentence(3),
            'event_type' => SchoolCalendarEventType::HOLIDAY->value,
            'start_date' => $startDate,
            'end_date' => $startDate,
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
