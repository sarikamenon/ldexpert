<?php

namespace Database\Factories;

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
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years')->format('Y-m-d'),
            'grade_level' => fake()->randomElement(['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']),
            'phone' => fake()->phoneNumber(),
            'emergency_contact' => fake()->phoneNumber(),
        ];
    }
}
