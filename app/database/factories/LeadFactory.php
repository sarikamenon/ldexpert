<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.3)->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->optional(0.7)->safeEmail(),
            'gender' => $this->faker->optional(0.5)->randomElement(['Male', 'Female', 'Non-binary']),
            'date_of_birth' => $this->faker->optional(0.5)->dateTimeBetween('-18 years', '-5 years'),
            'school_id' => School::factory(),
            'grade_level' => $this->faker->optional(0.5)->randomElement(['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']),
            'parent_guardian_name' => $this->faker->optional(0.6)->name(),
            'parent_guardian_email' => $this->faker->optional(0.6)->safeEmail(),
            'parent_guardian_phone' => $this->faker->optional(0.4)->numerify('###-###-####'),
            'address' => $this->faker->optional(0.4)->streetAddress(),
            'city' => $this->faker->optional(0.4)->city(),
            'state' => $this->faker->optional(0.4)->stateAbbr(),
            'zip_code' => $this->faker->optional(0.4)->postcode(),
            'status' => LeadStatus::INQUIRY->value,
            'source' => $this->faker->randomElement(LeadSource::options()),
            'follow_up_date' => $this->faker->optional(0.3)->dateTimeBetween('now', '+30 days'),
            'created_by' => User::factory()->admin(),
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn (): array => ['status' => LeadStatus::CONTACTED->value]);
    }

    public function followUp(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadStatus::FOLLOW_UP->value,
            'follow_up_date' => $this->faker->dateTimeBetween('now', '+14 days'),
        ]);
    }

    public function evaluation(): static
    {
        return $this->state(fn (): array => ['status' => LeadStatus::EVALUATION->value]);
    }

    public function enrolled(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadStatus::ENROLLED->value,
            'converted_student_id' => User::factory()->student(),
            'converted_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadStatus::DECLINED->value,
            'status_reason' => $this->faker->sentence(),
        ]);
    }

    public function overdueFollowUp(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadStatus::FOLLOW_UP->value,
            'follow_up_date' => $this->faker->dateTimeBetween('-14 days', '-1 day'),
        ]);
    }
}
