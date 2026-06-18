<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\AdminProfile;
use App\Models\ParentProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'password_change_prompted_at' => now(),
            'role' => Role::STUDENT->value,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model factory for therapist role.
     */
    public function therapist(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::THERAPIST->value,
        ]);
    }

    /**
     * Configure the model factory for student role.
     */
    public function student(): static
    {
        return $this->afterCreating(function ($user) {
            StudentProfile::factory()->create([
                'user_id' => $user->id,
            ]);
        })->state(fn (array $attributes) => [
            'username' => fake()->unique()->userName(),
            'role' => Role::STUDENT->value,
        ]);
    }

    /**
     * Configure the model factory for parent role.
     */
    public function parent(): static
    {
        return $this->afterCreating(function ($user) {
            ParentProfile::factory()->create([
                'user_id' => $user->id,
            ]);
        })->state(fn (array $attributes) => [
            'role' => Role::PARENT->value,
        ]);
    }

    /**
     * Configure the model factory for admin role.
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            AdminProfile::factory()->create([
                'user_id' => $user->id,
            ]);
        })->state(fn (array $attributes) => [
            'role' => Role::ADMIN->value,
        ]);
    }

    /**
     * Prefix email/username with a QA marker so QaDuskTestCase cleanup can
     * identify and delete the record (users.email LIKE 'qa.%').
     */
    public function qa(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => 'qa.'.fake()->unique()->safeEmail(),
            'username' => 'qa_'.fake()->unique()->userName(),
        ]);
    }
}
