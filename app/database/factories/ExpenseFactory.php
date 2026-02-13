<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'expense_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'vendor_payee' => $this->faker->optional()->company(),
            'description' => $this->faker->optional()->sentence(),
            'reference' => $this->faker->optional()->bothify('INV-####'),
            'created_by_id' => User::factory(),
        ];
    }
}
