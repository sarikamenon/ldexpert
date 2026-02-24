<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\TherapistTitle;
use App\Enums\UserStatus;
use App\Models\Position;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ScenarioTherapistSeeder extends Seeder
{
    /**
     * Create 30 therapists: 10 SLP, 10 OT, 10 other (PT, LCSW, SW, BCBA, RBT).
     */
    public function run(): void
    {
        $managerId = $this->resolveManagerId();
        $positions = Position::query()->active()->get()->keyBy('name');

        $slpId = $positions->get('SLP')?->id;
        $otId = $positions->get('OT')?->id;
        $others = [
            $positions->get('PT')?->id,
            $positions->get('LCSW')?->id,
            $positions->get('SW')?->id,
            $positions->get('BCBA')?->id,
            $positions->get('RBT')?->id,
        ];
        $others = array_filter($others);

        $created = 0;
        for ($i = 0; $i < 10 && $slpId; $i++) {
            $this->createTherapist($managerId, $slpId, 'SLP', $created + 1);
            $created++;
        }
        for ($i = 0; $i < 10 && $otId; $i++) {
            $this->createTherapist($managerId, $otId, 'OT', $created + 1);
            $created++;
        }
        $otherNames = ['PT', 'LCSW', 'SW', 'BCBA', 'RBT'];
        for ($i = 0; $i < 10 && ! empty($others); $i++) {
            $posId = $others[$i % count($others)];
            $name = $otherNames[$i % count($otherNames)];
            $this->createTherapist($managerId, $posId, $name, $created + 1);
            $created++;
        }
    }

    private function createTherapist(int $managerId, int $positionId, string $positionName, int $index): void
    {
        $user = User::query()->create([
            'name' => "Therapist {$positionName} {$index}",
            'email' => "therapist-{$positionName}-{$index}@example.com",
            'password' => Hash::make('password'),
            'role' => Role::THERAPIST->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        TherapistProfile::query()->create([
            'user_id' => $user->id,
            'employee_type' => EmployeeType::W2->value,
            'title' => TherapistTitle::MS->value,
            'first_name' => "First{$index}",
            'last_name' => "{$positionName}{$index}",
            'personal_email' => $user->email,
            'phone' => '555-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT).'-0000',
            'ld_email' => $user->email,
            'address' => '123 Scenario St',
            'comments' => 'Scenario 2025 therapist.',
            'position_id' => $positionId,
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => $managerId,
            'max_weekly_hours' => 40,
            'hourly_rate' => 75.00,
            'dob' => now()->subYears(32)->format('Y-m-d'),
        ]);
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
