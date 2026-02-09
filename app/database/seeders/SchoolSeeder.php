<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

final class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $managerId = $this->resolveManagerId();

        School::factory()
            ->count(15)
            ->state(fn () => ['manager_id' => $managerId])
            ->create()
            ->each(function (School $school): void {
                if (fake()->boolean(35)) {
                    $school->update([
                        'status' => SchoolStatus::INACTIVE->value,
                        'status_reason' => 'Seeder generated inactive school for regression testing.',
                    ]);
                }
            });
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
