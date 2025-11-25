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
        $faker = fake();
        $state = $faker->randomElement(array_keys(UsStates::STATES));
        $timezone = $faker->randomElement(array_keys(UsTimezones::TIMEZONES));

        return [
            'user_id' => User::factory()->therapist(),
            'employee_type' => $faker->randomElement(EmployeeType::cases())->value,
            'title' => $faker->randomElement(TherapistTitle::cases())->value,
            'first_name' => $faker->firstName(),
            'last_name' => $faker->lastName(),
            'personal_email' => $faker->unique()->safeEmail(),
            'phone' => $faker->numerify('###-###-####'),
            'ld_email' => $faker->optional(0.3)->safeEmail(),
            'address' => $faker->optional()->address(),
            'comments' => $faker->optional()->sentence(),
            'position' => $faker->randomElement(TherapistPosition::cases())->value,
            'state' => $state,
            'timezone' => $timezone,
            'manager_id' => User::factory()->admin(),
            'max_weekly_hours' => $faker->numberBetween(10, 60),
            'dob' => $faker->optional()->dateTimeBetween('-60 years', '-22 years'),
        ];
    }
}
