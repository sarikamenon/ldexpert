<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    protected $model = Audit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'auditable_type' => User::class,
            'auditable_id' => User::factory(),
            'event' => 'updated',
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
            'batch_uuid' => null,
            'source' => 'web',
            'url' => null,
            'ip_address' => null,
            'user_agent' => null,
        ];
    }
}
