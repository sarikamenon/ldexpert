<?php

namespace Database\Factories;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistPosition;
use App\Enums\TherapistTitle;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistProfile>
 */
class TherapistProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $state = $this->faker->randomElement(array_keys(UsStates::STATES));
        $timezone = $this->faker->randomElement(array_keys(UsTimezones::TIMEZONES));

        return [
            'user_id' => User::factory()->therapist(),
            'employee_type' => $this->faker->randomElement(EmployeeType::cases())->value,
            'title' => $this->faker->randomElement(TherapistTitle::cases())->value,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'personal_email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('###-###-####'),
            'ld_email' => $this->faker->optional(0.3)->safeEmail(),
            'address' => $this->faker->optional()->address(),
            'comments' => $this->faker->optional()->sentence(),
            'position' => $this->faker->randomElement(TherapistPosition::cases())->value,
            'state' => $state,
            'timezone' => $timezone,
            'manager_id' => User::factory()->admin(),
            'max_weekly_hours' => $this->faker->numberBetween(10, 60),
            'dob' => $this->faker->optional()->dateTimeBetween('-60 years', '-22 years'),
        ];
    }
}
