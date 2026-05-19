<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubRequestInviteeStatus;
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

    public function definition(): array
    {
        return [
            'schedule_sub_request_id' => ScheduleSubRequest::factory(),
            'therapist_id' => User::factory(),
            'status' => SubRequestInviteeStatus::INVITED->value,
            'responded_at' => null,
        ];
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestInviteeStatus::DECLINED->value,
            'responded_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestInviteeStatus::ACCEPTED->value,
            'responded_at' => now(),
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestInviteeStatus::SUPERSEDED->value,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubRequestInviteeStatus::WITHDRAWN->value,
        ]);
    }
}
