<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use App\Models\ScheduleMakeupRequest;
use App\Models\ScheduleMakeupRequestEmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleMakeupRequestEmailLog>
 */
class ScheduleMakeupRequestEmailLogFactory extends Factory
{
    protected $model = ScheduleMakeupRequestEmailLog::class;

    public function definition(): array
    {
        return [
            'schedule_makeup_request_id' => ScheduleMakeupRequest::factory(),
            'type' => ScheduleMakeupEmailLogType::REMINDER->value,
            'recipient_email' => $this->faker->safeEmail(),
            'recipient_name' => $this->faker->name(),
            'from_email' => $this->faker->safeEmail(),
            'from_name' => $this->faker->name(),
            'subject' => $this->faker->sentence(6),
            'status' => ScheduleMakeupEmailLogStatus::QUEUED->value,
            'sent_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupEmailLogStatus::SENT->value,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Send failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleMakeupEmailLogStatus::FAILED->value,
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }
}
