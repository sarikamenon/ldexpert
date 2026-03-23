<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistBillPaymentAllocation>
 */
class TherapistBillPaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'therapist_bill_id' => TherapistBill::factory(),
            'therapist_bill_payment_id' => TherapistBillPayment::factory(),
            'allocated_amount' => $this->faker->randomFloat(2, 10, 5000),
        ];
    }
}
