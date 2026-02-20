<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoicePaymentAllocation>
 */
class InvoicePaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'invoice_payment_id' => InvoicePayment::factory(),
            'allocated_amount' => $this->faker->randomFloat(2, 10, 5000),
        ];
    }
}
