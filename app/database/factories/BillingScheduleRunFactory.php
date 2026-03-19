<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingScheduleRunStatus;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingScheduleRun>
 */
class BillingScheduleRunFactory extends Factory
{
    protected $model = BillingScheduleRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_schedule_id' => BillingSchedule::factory(),
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->endOfMonth()->toDateString(),
            'generation_date' => now()->toDateString(),
            'status' => BillingScheduleRunStatus::SUCCESS->value,
            'sessions_found' => $this->faker->numberBetween(1, 20),
            'sessions_from_prior_periods' => 0,
            'adjustments_count' => 0,
            'adjustment_total' => 0,
            'carry_forward_amount' => 0,
            'invoice_id' => null,
            'therapist_bill_id' => null,
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'auto_sent' => false,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingScheduleRunStatus::SUCCESS->value,
        ]);
    }

    public function failed(string $message = 'An error occurred'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingScheduleRunStatus::FAILED->value,
            'error_message' => $message,
            'sessions_found' => 0,
            'total_amount' => null,
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value,
            'sessions_found' => 0,
            'total_amount' => null,
        ]);
    }
}
