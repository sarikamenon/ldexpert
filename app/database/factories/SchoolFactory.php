<?php

namespace Database\Factories;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Enums\SchoolStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
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
            'full_name' => $this->faker->company().' School',
            'display_name' => $this->faker->company() . ' ' . Str::random(6),
            'address' => $this->faker->address(),
            'state' => $state,
            'timezone' => $timezone,
            'manager_id' => User::factory()->admin(),
            'contact_first_name' => $this->faker->firstName(),
            'contact_last_name' => $this->faker->lastName(),
            'contact_phone' => $this->faker->numerify('###-###-####'),
            'contact_email' => $this->faker->safeEmail(),
            'invoice_email' => $this->faker->safeEmail(),
            'school_type' => $this->faker->randomElement(['Virtual', 'Brick Mortar', 'Blended']),
            'is_private_student' => $this->faker->boolean(),
            'non_billable_scheduling' => $this->faker->boolean(),
            'external_emr_name' => $this->faker->optional()->company(),
            'status' => SchoolStatus::ACTIVE->value,
            'status_reason' => null,
        ];
    }
}
