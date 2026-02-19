<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistTitle;
use App\Enums\UserStatus;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TherapistSeeder extends Seeder
{
    public function run(): void
    {
        $managerId = $this->resolveManagerId();
        $email = env('THERAPIST_EMAIL', 'therapist@example.com');
        $password = env('THERAPIST_PASSWORD', 'Temp1234!');
        $personalEmail = env('THERAPIST_PERSONAL_EMAIL', $email);
        $ldEmail = env('THERAPIST_LD_EMAIL', $email);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('THERAPIST_NAME', 'Default Therapist'),
                'password' => Hash::make($password),
                'role' => Role::THERAPIST->value,
                'status' => UserStatus::ACTIVE->value,
            ]
        );

        TherapistProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_type' => env('THERAPIST_EMPLOYEE_TYPE', EmployeeType::W2->value),
                'title' => env('THERAPIST_TITLE', TherapistTitle::MS->value),
                'first_name' => env('THERAPIST_FIRST_NAME', 'Taylor'),
                'last_name' => env('THERAPIST_LAST_NAME', 'Morgan'),
                'personal_email' => $personalEmail,
                'phone' => env('THERAPIST_PHONE', '555-123-9876'),
                'ld_email' => $ldEmail,
                'address' => env('THERAPIST_ADDRESS', '1234 Therapy Lane, San Diego, CA 92101'),
                'comments' => env('THERAPIST_COMMENTS', 'Seeder generated therapist account for manual QA.'),
                'position' => env('THERAPIST_POSITION', 'SLP'),
                'state' => env('THERAPIST_STATE', 'CA'),
                'timezone' => env('THERAPIST_TIMEZONE', 'America/Los_Angeles'),
                'manager_id' => $managerId,
                'max_weekly_hours' => (int) env('THERAPIST_MAX_WEEKLY_HOURS', 40),
                'dob' => env('THERAPIST_DOB', now()->subYears(32)->format('Y-m-d')),
            ]
        );

        TherapistProfile::factory()
            ->count(15)
            ->state(fn () => ['manager_id' => $managerId])
            ->create()
            ->each(function (TherapistProfile $profile): void {
                if (fake()->boolean(40)) {
                    $profile->user->update([
                        'status' => UserStatus::INACTIVE->value,
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
