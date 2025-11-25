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
        $state = fake()->randomElement(array_keys(UsStates::STATES));
        $timezone = fake()->randomElement(array_keys(UsTimezones::TIMEZONES));

        return [
            'user_id' => User::factory()->therapist(),
            'employee_type' => fake()->randomElement(EmployeeType::cases())->value,
            'title' => fake()->randomElement(TherapistTitle::cases())->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'personal_email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'ld_email' => fake()->optional(0.3)->safeEmail(),
            'address' => fake()->optional()->address(),
            'comments' => fake()->optional()->sentence(),
            'position' => fake()->randomElement(TherapistPosition::cases())->value,
            'state' => $state,
            'timezone' => $timezone,
            'manager_id' => User::factory()->admin(),
            'max_weekly_hours' => fake()->numberBetween(10, 60),
            'dob' => fake()->optional()->dateTimeBetween('-60 years', '-22 years'),
        ];
    }
}
