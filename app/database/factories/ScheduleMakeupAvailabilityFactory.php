<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleMakeupAvailability>
 */
class ScheduleMakeupAvailabilityFactory extends Factory
{
    protected $model = ScheduleMakeupAvailability::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');

        return [
            'therapist_id' => User::factory(),
            'availability_date' => $date,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'notes' => null,
        ];
    }
}
