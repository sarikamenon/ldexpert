<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSubRequestInvitee>
 */
class ScheduleSubRequestInviteeFactory extends Factory
{
    protected $model = ScheduleSubRequestInvitee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_sub_request_id' => ScheduleSubRequest::factory(),
            'therapist_id' => User::factory(),
            'status' => 'invited',
            'responded_at' => null,
        ];
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'superseded',
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'withdrawn',
        ]);
    }
}
