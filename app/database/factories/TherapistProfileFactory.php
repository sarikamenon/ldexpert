<?php

namespace Database\Factories;

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
        return [
            'user_id' => User::factory(),
            'phone' => fake()->phoneNumber(),
            'license_number' => fake()->unique()->numerify('LIC-####'),
            'specialization' => fake()->randomElement(['Speech Therapy', 'Occupational Therapy', 'Physical Therapy', 'Behavioral Therapy']),
            'years_of_experience' => fake()->numberBetween(1, 30),
            'bio' => fake()->paragraph(),
        ];
    }
}
