<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RateType;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLog>
 */
class SessionLogFactory extends Factory
{
    protected $model = SessionLog::class;

    public function definition(): array
    {
        return [
            'therapist_id' => User::factory()->therapist(),
            'student_id' => User::factory()->student(),
            'ssa_id' => ServiceSupportAgreement::factory(),
            'schedule_id' => null,
            'service_id' => Service::factory(),
            'school_id' => School::factory(),
            'session_date' => $this->faker->date(),
            'start_time' => $this->faker->dateTime(),
            'end_time' => $this->faker->dateTime(),
            'duration_minutes' => 60,
            'tho_minutes' => 30,
            'notes' => $this->faker->paragraph(3),
            'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
            'delivery_mode' => 'virtual',
            'is_group' => false,
            'is_billable_therapist' => true,
            'therapist_contract_id' => null,
            'therapist_rate_type' => RateType::HOURLY,
            'therapist_rate_amount' => 100.00,
            'therapist_billable_amount' => 100.00,
            'therapist_bill_id' => null,
            'is_billable_school' => true,
            'school_contract_id' => null,
            'school_rate_type' => RateType::HOURLY,
            'school_rate_amount' => 150.00,
            'school_invoice_amount' => 150.00,
            'invoice_id' => null,
            'is_rate_override' => false,
            'override_reason' => null,
            'status' => SessionLogStatus::DRAFT,
            'submitted_at' => null,
            'submitted_by_id' => null,
            'approved_at' => null,
            'approved_by_id' => null,
            'sent_back_at' => null,
            'sent_back_by_id' => null,
            'cancellation_reason' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionLogStatus::DRAFT,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionLogStatus::SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_id' => $attributes['therapist_id'],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionLogStatus::APPROVED,
            'submitted_at' => now()->subDay(),
            'submitted_by_id' => $attributes['therapist_id'],
            'approved_at' => now(),
            'approved_by_id' => User::factory()->admin(),
        ]);
    }

    public function sentBack(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionLogStatus::SENT_BACK,
            'submitted_at' => now()->subDay(),
            'submitted_by_id' => $attributes['therapist_id'],
            'sent_back_at' => now(),
            'sent_back_by_id' => User::factory()->admin(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionLogStatus::CANCELLED,
            'cancellation_reason' => 'Test cancellation',
        ]);
    }

    public function withSchedule(Schedule $schedule): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_id' => $schedule->id,
            'therapist_id' => $schedule->therapist_id,
            'student_id' => $schedule->student_id,
            'ssa_id' => $schedule->ssa_id,
            'service_id' => $schedule->service_id,
            'school_id' => $schedule->school_id,
        ]);
    }
}
