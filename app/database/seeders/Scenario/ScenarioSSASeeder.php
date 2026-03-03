<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class ScenarioSSASeeder extends Seeder
{
    /**
     * Create 75 SSAs (one per student). Start 2025-08-01 to 2025-12-31, end 2026-03-31 to 2026-07-31.
     * Assign therapists to all SSAs (align SLP->Speech Therapy, OT->Occupational Therapy).
     */
    public function run(): void
    {
        $students = User::query()
            ->where('role', Role::STUDENT->value)
            ->where('email', 'like', 'scenario-student-%@example.com')
            ->orderBy('id')
            ->get();

        if ($students->count() < 75) {
            $this->command?->warn('ScenarioSSASeeder: expected 75 scenario students.');

            return;
        }

        $speechService = Service::query()->where('name', 'Speech Therapy')->first()
            ?? Service::query()->where('name', 'like', '%Speech%')->first();
        $otService = Service::query()->where('name', 'Occupational Therapy')->first()
            ?? Service::query()->where('name', 'like', '%Occupational%')->first();
        $fallbackService = $speechService ?? $otService ?? Service::query()->active()->first();
        if (! $fallbackService) {
            $this->command->error('ScenarioSSASeeder: no services found.');

            return;
        }

        $therapistsByPosition = $this->therapistsByPosition();

        $adminId = User::query()->where('role', Role::ADMIN->value)->value('id') ?? 1;

        foreach ($students as $index => $student) {
            $startDate = Carbon::create(2025, 8, 1)->addDays(random_int(0, 152)); // Aug 1 - Dec 31
            $endDate = Carbon::create(2026, 3, 31)->addDays(random_int(0, 122)); // Mar 31 - Jul 31
            if ($endDate->lt($startDate)) {
                $endDate = $startDate->copy()->addMonths(6);
            }

            $minutesPerSession = [30, 45, 60][$index % 3];
            $frequency = ServiceFrequency::WEEKLY;
            $sessionsPerFrequency = 2;
            $daysDiff = $startDate->diffInDays($endDate) + 1;
            $thoMinutes = (int) ceil($daysDiff * (52 / 365)) * $sessionsPerFrequency * $minutesPerSession;

            $primaryService = $fallbackService;
            $therapist = null;
            if (($index % 2 === 0) && $speechService && $therapistsByPosition->has('SLP')) {
                $primaryService = $speechService;
                $therapist = $therapistsByPosition->get('SLP')->random();
            } elseif ($therapistsByPosition->has('OT') && $otService) {
                $primaryService = $otService;
                $therapist = $therapistsByPosition->get('OT')->random();
            }
            if (! $therapist && $therapistsByPosition->isNotEmpty()) {
                $therapist = $therapistsByPosition->flatten()->random();
            }

            $ssa = ServiceSupportAgreement::query()->create([
                'student_id' => $student->id,
                'primary_service_id' => $primaryService->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'minutes_per_session' => $minutesPerSession,
                'frequency' => $frequency->value,
                'sessions_per_frequency' => $sessionsPerFrequency,
                'tho_minutes' => $thoMinutes,
                'assigned_therapist_id' => $therapist?->id,
                'status' => SSAStatus::ACTIVE->value,
                'served_minutes' => 0,
            ]);

            $ssa->services()->sync([$primaryService->id => ['is_primary' => true]]);

            if ($therapist) {
                $ssa->assignmentHistory()->create([
                    'therapist_id' => $therapist->id,
                    'action' => 'assigned',
                    'assigned_by' => $adminId,
                    'assigned_at' => $ssa->created_at,
                ]);
            }
        }
    }

    /**
     * @return Collection<string, Collection<int, User>>
     */
    private function therapistsByPosition(): Collection
    {
        $therapists = User::query()
            ->where('role', Role::THERAPIST->value)
            ->with('therapistProfile.position')
            ->get();

        $byPosition = collect();
        foreach ($therapists as $user) {
            $name = $user->therapistProfile?->position?->name ?? 'Other';
            if (! $byPosition->has($name)) {
                $byPosition->put($name, collect());
            }
            $byPosition->get($name)->push($user);
        }

        return $byPosition;
    }
}
