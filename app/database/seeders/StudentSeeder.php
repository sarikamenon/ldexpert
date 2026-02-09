<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\School;
use App\Models\StudentProfile;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Seed demo student accounts with guardian + school context.
     */
    public function run(): void
    {
        $schoolIds = School::query()->pluck('id');

        if ($schoolIds->isEmpty()) {
            $schoolIds = School::factory()
                ->count(5)
                ->create()
                ->pluck('id');
        }

        StudentProfile::factory()
            ->count(15)
            ->state(fn () => ['school_id' => $schoolIds->random()])
            ->create()
            ->each(function (StudentProfile $profile): void {
                if (fake()->boolean(30)) {
                    $profile->user->update(['status' => UserStatus::INACTIVE->value]);
                }
            });
    }
}
