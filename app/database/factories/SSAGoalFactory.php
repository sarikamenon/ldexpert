<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SSAGoalStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SSAGoal>
 */
final class SSAGoalFactory extends Factory
{
    protected $model = SSAGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ssa = ServiceSupportAgreement::factory()->create();

        return [
            'ssa_id' => $ssa->id,
            'student_id' => $ssa->student_id,
            'number' => (string) $this->faker->numberBetween(1, 20),
            'objective' => $this->faker->sentence(12),
            'progress' => $this->faker->optional()->sentence(8),
            'status' => SSAGoalStatus::ACTIVE->value,
        ];
    }

    public function mastered(): self
    {
        return $this->state(fn () => ['status' => SSAGoalStatus::MASTERED->value]);
    }

    public function discontinued(): self
    {
        return $this->state(fn () => ['status' => SSAGoalStatus::DISCONTINUED->value]);
    }
}
