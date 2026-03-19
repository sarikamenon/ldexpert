<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingReminderType;
use App\Models\BillingReminder;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingReminder>
 */
class BillingReminderFactory extends Factory
{
    protected $model = BillingReminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'remindable_type' => Invoice::class,
            'remindable_id' => Invoice::factory(),
            'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
            'sent_at' => now(),
        ];
    }

    public function upcomingDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => BillingReminderType::OVERDUE->value,
        ]);
    }

    public function overdueFollowup(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => BillingReminderType::OVERDUE_FOLLOWUP->value,
        ]);
    }
}
