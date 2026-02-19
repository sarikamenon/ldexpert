<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistBillPayment>
 */
class TherapistBillPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'therapist_id' => User::factory(),
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'method' => $this->faker->randomElement(PaymentMethod::cases()),
            'reference' => $this->faker->optional()->bothify('CHK-####'),
            'notes' => $this->faker->optional()->sentence(),
            'recorded_by_id' => User::factory(),
        ];
    }
}
