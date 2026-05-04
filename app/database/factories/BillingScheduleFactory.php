<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\GenerationDayType;
use App\Models\BillingSchedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingSchedule>
 */
class BillingScheduleFactory extends Factory
{
    protected $model = BillingSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedulable_type' => School::class,
            'schedulable_id' => School::factory(),
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
            'billing_mode' => BillingMode::STANDARD->value,
            'frequency' => BillingFrequency::SEMI_MONTHLY->value,
            'generation_day_type' => GenerationDayType::DAY_OF_WEEK->value,
            'generation_day_of_week' => 2,
            'generation_delay_days' => null,
            'min_grace_days' => 2,
            'payment_terms_days' => 30,
            'auto_generate' => true,
            'auto_send' => false,
            'is_active' => true,
            'last_run_at' => null,
            'last_period_end' => null,
            'next_run_at' => now()->addDays(5)->toDateString(),
            'notes' => null,
        ];
    }

    public function forSchool(?School $school = null): static
    {
        return $this->state(fn (array $attributes) => [
            'schedulable_type' => School::class,
            'schedulable_id' => $school?->id ?? School::factory(),
            'schedule_type' => BillingScheduleType::SCHOOL_INVOICE->value,
        ]);
    }

    public function forTherapist(?User $therapist = null): static
    {
        return $this->state(fn (array $attributes) => [
            'schedulable_type' => User::class,
            'schedulable_id' => $therapist?->id ?? User::factory()->therapist(),
            'schedule_type' => BillingScheduleType::THERAPIST_BILL->value,
        ]);
    }

    public function advance(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_mode' => BillingMode::ADVANCE->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'auto_generate' => true,
            'next_run_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function withFixedDelay(int $days = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'generation_day_type' => GenerationDayType::FIXED_DELAY->value,
            'generation_day_of_week' => null,
            'generation_delay_days' => $days,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => BillingFrequency::WEEKLY->value,
        ]);
    }

    public function biWeekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => BillingFrequency::BI_WEEKLY->value,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => BillingFrequency::MONTHLY->value,
        ]);
    }
}
