<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class SSASeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', Role::STUDENT)
            ->where('status', UserStatus::ACTIVE)
            ->with('studentProfile')
            ->get();

        $therapists = User::where('role', Role::THERAPIST)
            ->where('status', UserStatus::ACTIVE)
            ->get();

        $services = Service::where('status', ServiceStatus::ACTIVE)->get();
        $indirectServices = $services->where('is_direct_service', false);

        if ($students->isEmpty() || $services->isEmpty()) {
            $this->command->warn('No active students or services found. Skipping SSA seeding.');

            return;
        }

        $frequencies = [
            ServiceFrequency::WEEKLY,
            ServiceFrequency::BI_WEEKLY,
            ServiceFrequency::MONTHLY,
            ServiceFrequency::QUARTERLY,
        ];

        // Create SSAs for a subset of students
        $studentsToSeed = $students->random(min(10, $students->count()));

        foreach ($studentsToSeed as $student) {
            $service = $services->random();
            $frequency = $frequencies[array_rand($frequencies)];

            // Determine dates
            $startDate = now()->subMonths(rand(0, 6))->startOfMonth();
            $endDate = $startDate->copy()->addYear();

            // Calculate sessions per frequency
            $sessionsPerFrequency = match ($frequency) {
                ServiceFrequency::WEEKLY => rand(1, 3),
                ServiceFrequency::BI_WEEKLY => rand(1, 2),
                ServiceFrequency::MONTHLY => rand(1, 4),
                ServiceFrequency::QUARTERLY => rand(1, 2),
            };

            $minutesPerSession = [30, 45, 60][array_rand([30, 45, 60])];

            // Calculate THO minutes
            $daysDiff = $startDate->diffInDays($endDate) + 1;
            $frequencyMultiplier = match ($frequency) {
                ServiceFrequency::WEEKLY => 52 / 365,
                ServiceFrequency::BI_WEEKLY => 26 / 365,
                ServiceFrequency::MONTHLY => 12 / 365,
                ServiceFrequency::QUARTERLY => 4 / 365,
            };
            $numberOfFrequencies = (int) ceil($daysDiff * $frequencyMultiplier);
            $totalSessions = $numberOfFrequencies * $sessionsPerFrequency;
            $thoMinutes = $totalSessions * $minutesPerSession;

            // First decide if we assign a therapist (50% chance)
            $assignedTherapist = null;
            if ($therapists->isNotEmpty() && rand(1, 100) <= 50) {
                $assignedTherapist = $therapists->random();
            }

            // Set status based on therapist assignment
            // If therapist assigned: active or completed
            // If no therapist: pending or deactivated
            if ($assignedTherapist !== null) {
                $status = rand(1, 100) <= 70 ? SSAStatus::ACTIVE : SSAStatus::COMPLETED;
            } else {
                $status = rand(1, 100) <= 60 ? SSAStatus::PENDING : SSAStatus::DEACTIVATED;
            }

            // Calculate served minutes (only for active/completed SSAs)
            $servedMinutes = 0;
            if ($status === SSAStatus::ACTIVE) {
                $servedMinutes = (int) ($thoMinutes * (rand(10, 80) / 100)); // 10-80% served
            } elseif ($status === SSAStatus::COMPLETED) {
                $servedMinutes = (int) ($thoMinutes * (rand(85, 100) / 100)); // 85-100% served
            }

            $ssa = ServiceSupportAgreement::create([
                'student_id' => $student->id,
                'primary_service_id' => $service->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'minutes_per_session' => $minutesPerSession,
                'frequency' => $frequency->value,
                'sessions_per_frequency' => $sessionsPerFrequency,
                'tho_minutes' => $thoMinutes,
                'assigned_therapist_id' => $assignedTherapist?->id,
                'status' => $status->value,
                'served_minutes' => $servedMinutes,
            ]);

            // Create assignment history if therapist is assigned
            if ($assignedTherapist) {
                $ssa->assignmentHistory()->create([
                    'therapist_id' => $assignedTherapist->id,
                    'action' => 'assigned',
                    'assigned_by' => User::where('role', Role::ADMIN)->first()?->id ?? 1,
                    'assigned_at' => $ssa->created_at,
                ]);
            }

            $servicePayload = [
                $service->id => ['is_primary' => true],
            ];

            if ($indirectServices->isNotEmpty() && rand(1, 100) <= 40) {
                $selection = $indirectServices->random(rand(1, min(3, $indirectServices->count())));
                $selectedIds = $selection instanceof Collection
                    ? $selection->pluck('id')->all()
                    : [$selection->id];

                foreach ($selectedIds as $serviceId) {
                    if ($serviceId === $service->id) {
                        continue;
                    }

                    $servicePayload[$serviceId] = ['is_primary' => false];
                }
            }

            $ssa->services()->sync($servicePayload);
        }

        $this->command->info('SSA seeder completed. Created '.$studentsToSeed->count().' SSAs.');
    }
}
