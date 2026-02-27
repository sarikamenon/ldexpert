<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ScenarioStudentSeeder extends Seeder
{
    /**
     * Create 75 students (5 per school), all active.
     */
    public function run(): void
    {
        $schools = School::query()->orderBy('id')->get();
        if ($schools->count() < 15) {
            $this->command?->warn('ScenarioStudentSeeder: expected at least 15 schools.');

            return;
        }

        $index = 0;
        foreach ($schools as $school) {
            for ($j = 0; $j < 5; $j++) {
                $index++;
                $user = User::query()->create([
                    'name' => "Scenario Student {$index}",
                    'username' => "scenario.student.{$index}",
                    'email' => "scenario-student-{$index}@example.com",
                    'password' => Hash::make('password'),
                    'role' => Role::STUDENT->value,
                    'status' => UserStatus::ACTIVE->value,
                ]);

                StudentProfile::query()->create([
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                    'first_name' => "First{$index}",
                    'last_name' => "Student{$index}",
                    'timezone' => 'America/Los_Angeles',
                    'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
                    'grade_level' => '5',
                ]);
            }
        }
    }
}
