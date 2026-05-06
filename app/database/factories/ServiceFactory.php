<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
final class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $minDuration = $this->faker->numberBetween(15, 60);
        $maxDuration = $minDuration + $this->faker->numberBetween(15, 45);

        $deliveryModes = array_keys(Service::deliveryModeOptions());

        return [
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->sentence(10),
            'color' => null,
            'is_direct_service' => $this->faker->boolean(80),
            'is_group_service' => $this->faker->boolean(40),
            'is_frequency_service' => $this->faker->boolean(70),
            'delivery_mode' => $this->faker->randomElement($deliveryModes),
            'is_billable' => $this->faker->boolean(90),
            'send_email' => true,
            'min_duration_minutes' => $minDuration,
            'max_duration_minutes' => $maxDuration,
            'status' => ServiceStatus::ACTIVE->value,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => [
            'status' => ServiceStatus::INACTIVE->value,
        ]);
    }
}
