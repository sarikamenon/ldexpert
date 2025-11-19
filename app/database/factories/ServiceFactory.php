<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceFrequency;
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
        $frequencies = ServiceFrequency::cases();
        $frequency = $this->faker->randomElement($frequencies)->value;

        $minDuration = $this->faker->numberBetween(15, 60);
        $maxDuration = $minDuration + $this->faker->numberBetween(15, 45);

        $deliveryModes = array_keys(Service::deliveryModeOptions());

        return [
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->sentence(10),
            'direct_service' => $this->faker->boolean(80),
            'group_service' => $this->faker->boolean(40),
            'frequency' => $frequency,
            'delivery_mode' => $this->faker->randomElement($deliveryModes),
            'is_billable' => $this->faker->boolean(90),
            'min_duration_minutes' => $minDuration,
            'max_duration_minutes' => $maxDuration,
            'status' => ServiceStatus::ACTIVE->value,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn() => [
            'status' => ServiceStatus::INACTIVE->value,
        ]);
    }
}
