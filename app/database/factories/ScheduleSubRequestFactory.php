<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSubRequest>
 */
class ScheduleSubRequestFactory extends Factory
{
    protected $model = ScheduleSubRequest::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'requested_by_id' => User::factory(),
            'reason' => $this->faker->optional()->sentence(),
            'status' => SubRequestStatus::OPEN->value,
            'accepted_by_id' => null,
            'accepted_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function accepted(User $sub): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestStatus::ACCEPTED->value,
            'accepted_by_id' => $sub->id,
            'accepted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
    }
}
