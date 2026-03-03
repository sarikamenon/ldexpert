<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ScenarioSchoolSeeder extends Seeder
{
    /**
     * Create 15 US schools, all active, for 2025 scenario.
     */
    public function run(): void
    {
        $managerId = $this->resolveManagerId();

        School::factory()
            ->count(15)
            ->state(fn () => [
                'manager_id' => $managerId,
                'status' => SchoolStatus::ACTIVE->value,
                'status_reason' => null,
            ])
            ->create();
    }

    private function resolveManagerId(): int
    {
        $manager = User::query()->where('role', Role::ADMIN->value)->first();

        if ($manager) {
            return $manager->id;
        }

        return User::factory()->admin()->create()->id;
    }
}
