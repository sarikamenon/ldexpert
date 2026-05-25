<?php

namespace Database\Factories;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\Role;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
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
            'user_id' => User::factory()->state(['role' => Role::STUDENT->value]),
            'parent_id' => null,
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'school_id' => School::factory(),
            'id_number' => $this->faker->optional()->numerify('STU####'),
            'timezone' => $timezone,
            'gender' => $this->faker->optional()->randomElement(['Male', 'Female', 'Other', 'Prefer not to say']),
            'address' => $this->faker->optional()->address(),
            'city' => $this->faker->optional()->city(),
            'state' => $state,
            'zip_code' => $this->faker->optional()->postcode(),
            'parent_guardian_name' => $this->faker->optional()->name(),
            'parent_guardian_email' => $this->faker->optional()->safeEmail(),
            'parent_guardian_phone' => $this->faker->optional()->numerify('###-###-####'),
            'schedule_email' => $this->faker->optional()->safeEmail(),
            'parent_guardian_2_name' => $this->faker->optional()->name(),
            'parent_guardian_2_email' => $this->faker->optional()->safeEmail(),
            'parent_guardian_2_phone' => $this->faker->optional()->numerify('###-###-####'),
            'date_of_birth' => $this->faker->dateTimeBetween('-18 years', '-5 years'),
            'grade_level' => $this->faker->randomElement(['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']),
        ];
    }
}
