<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
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

        $invoiceNumber = sprintf(
            'INV-%s-%03d',
            now()->format('Ymd'),
            self::$sequence++
        );

        return [
            'school_id' => School::factory(),
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now(),
            'billing_period_start' => $startDate,
            'billing_period_end' => $endDate,
            'status' => InvoiceStatus::DRAFT->value,
            'subtotal' => $this->faker->randomFloat(2, 100, 10000),
            'tax_total' => 0.00,
            'total' => fn (array $attributes) => $attributes['subtotal'] + $attributes['tax_total'],
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'sent_at' => null,
            'sent_by_id' => null,
            'notes' => $this->faker->optional()->sentence(),
            'school_name' => $this->faker->company().' School',
            'school_display_name' => $this->faker->company(),
            'school_address' => $this->faker->address(),
            'school_state' => $this->faker->randomElement(['CA', 'NY', 'TX', 'FL']),
            'school_contact_first_name' => $this->faker->firstName(),
            'school_contact_last_name' => $this->faker->lastName(),
            'school_contact_phone' => $this->faker->phoneNumber(),
            'school_contact_email' => $this->faker->email(),
            'school_invoice_email' => $this->faker->email(),
            'company_name' => 'LD Expert LLP',
            'company_address' => $this->faker->address(),
            'company_phone' => $this->faker->phoneNumber(),
            'company_email' => $this->faker->companyEmail(),
            'company_tax_id' => $this->faker->optional()->numerify('TAX-####'),
        ];
    }

    /**
     * Indicate that the invoice is sent.
     */
    public function sent(?User $sentBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::SENT->value,
            'sent_at' => now(),
            'sent_by_id' => $sentBy?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PAID->value,
        ]);
    }
}
