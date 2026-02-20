<?php

namespace Database\Factories;

use App\Enums\TherapistBillStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistBill>
 */
class TherapistBillFactory extends Factory
{
    private static int $sequence = 1;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-3 months', '-1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, 'now');

        $billNumber = sprintf(
            'BILL-%s-%03d',
            now()->format('Ymd'),
            self::$sequence++
        );

        return [
            'therapist_id' => User::factory(),
            'bill_number' => $billNumber,
            'bill_date' => now(),
            'billing_period_start' => $startDate,
            'billing_period_end' => $endDate,
            'status' => TherapistBillStatus::DRAFT->value,
            'subtotal' => $this->faker->randomFloat(2, 100, 10000),
            'adjustments_total' => 0.00,
            'total_due' => fn (array $attributes) => $attributes['subtotal'] + $attributes['adjustments_total'],
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'sent_at' => null,
            'sent_by_id' => null,
            'notes' => $this->faker->optional()->sentence(),
            'therapist_name' => $this->faker->name(),
            'therapist_email' => $this->faker->email(),
            'therapist_phone' => $this->faker->phoneNumber(),
            'therapist_address' => $this->faker->address(),
            'company_name' => 'LD Expert LLP',
            'company_address' => $this->faker->address(),
            'company_phone' => $this->faker->phoneNumber(),
            'company_email' => $this->faker->companyEmail(),
            'company_tax_id' => $this->faker->optional()->numerify('TAX-####'),
        ];
    }

    /**
     * Indicate that the bill is sent.
     */
    public function sent(?User $sentBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TherapistBillStatus::SENT->value,
            'sent_at' => now(),
            'sent_by_id' => $sentBy?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the bill is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TherapistBillStatus::PAID->value,
        ]);
    }
}
