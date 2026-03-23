<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSupportAgreement>
 */
final class ServiceSupportAgreementFactory extends Factory
{
    protected $model = ServiceSupportAgreement::class;

    public function configure(): self
    {
        return $this->afterCreating(function (ServiceSupportAgreement $ssa): void {
            if ($ssa->primary_service_id) {
                $ssa->services()->sync([
                    $ssa->primary_service_id => ['is_primary' => true],
                ]);
            }
        });
    }

    public function definition(): array
    {
        $startDate = now()->addDays(1);
        $endDate = $startDate->copy()->addYear();
        $recurringFrequencies = [
            ServiceFrequency::WEEKLY,
            ServiceFrequency::BI_WEEKLY,
            ServiceFrequency::MONTHLY,
            ServiceFrequency::QUARTERLY,
        ];

        return [
            'student_id' => User::factory()->create(['role' => 'student'])->id,
            'primary_service_id' => Service::factory()->create()->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'minutes_per_session' => $this->faker->randomElement([30, 45, 60]),
            'frequency' => $this->faker->randomElement($recurringFrequencies),
            'sessions_per_frequency' => $this->faker->numberBetween(1, 4),
            'tho_minutes' => $this->faker->numberBetween(1000, 5000),
            'assigned_therapist_id' => null,
            'status' => SSAStatus::PENDING->value,
            'served_minutes' => 0,
        ];
    }

    public function withTherapist(): self
    {
        return $this->state(fn () => [
            'assigned_therapist_id' => User::factory()->create(['role' => 'therapist'])->id,
        ]);
    }

    public function active(): self
    {
        return $this->state(fn () => [
            'status' => SSAStatus::ACTIVE->value,
            'assigned_therapist_id' => User::factory()->create(['role' => 'therapist'])->id,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => SSAStatus::COMPLETED->value,
        ]);
    }
}
