<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Constants\ServiceCatalog;
use App\Enums\ContractStatus;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsFixedSchoolYear;
use Illuminate\Database\Seeder;

final class ScenarioTherapistContractSeeder extends Seeder
{
    use SeedsFixedSchoolYear;

    /**
     * 10 therapists get one full-year contract; 5 get two 6-month contracts. Services from position.
     */
    public function run(): void
    {
        $schoolYear = $this->fixedSchoolYear2025();
        $fullYearStart = $schoolYear['start'];
        $fullYearEnd = $schoolYear['end'];
        $split1End = Carbon::create(2026, 1, 31)->endOfDay();
        $split2Start = Carbon::create(2026, 2, 1)->startOfDay();

        $rateByServiceName = $this->rateMap();

        $profiles = TherapistProfile::query()->orderBy('id')->get();
        if ($profiles->count() < 15) {
            $this->command?->warn('ScenarioTherapistContractSeeder: expected at least 15 therapist profiles.');

            return;
        }

        foreach ($profiles->take(10) as $profile) {
            $this->createContractWithPositionServices($profile, $fullYearStart, $fullYearEnd, $rateByServiceName);
        }

        foreach ($profiles->slice(10, 5) as $profile) {
            $this->createContractWithPositionServices($profile, $fullYearStart, $split1End, $rateByServiceName);
            $this->createContractWithPositionServices($profile, $split2Start, $fullYearEnd, $rateByServiceName);
        }
    }

    /**
     * Therapist rates are 75% of catalog (school) rates so the agency retains a ~25% margin.
     *
     * @return array<string, array{rate: float, rate_type: string}>
     */
    private function rateMap(): array
    {
        $margin = 0.75;
        $map = [];
        foreach (ServiceCatalog::services() as $row) {
            $map[$row['name']] = [
                'rate' => round((float) $row['rate'] * $margin, 2),
                'rate_type' => $row['rate_type'],
                'no_show_rate' => round((float) ($row['no_show_rate'] ?? 0) * $margin, 2),
                'no_show_rate_type' => $row['no_show_rate_type'] ?? $row['rate_type'],
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{rate: float, rate_type: string, no_show_rate?: float, no_show_rate_type?: string}>  $rateByServiceName
     */
    private function createContractWithPositionServices(
        TherapistProfile $profile,
        Carbon $start,
        Carbon $end,
        array $rateByServiceName
    ): void {
        $contract = TherapistContract::query()->create([
            'therapist_id' => $profile->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'notes' => 'Scenario 2025 therapist contract.',
        ]);

        $profile->load('position.services');
        $position = $profile->position;
        if (! $position) {
            return;
        }

        foreach ($position->services as $service) {
            $rates = $rateByServiceName[$service->name] ?? ['rate' => 85.0, 'rate_type' => 'H', 'no_show_rate' => 0, 'no_show_rate_type' => 'H'];
            TherapistContractService::query()->create([
                'therapist_contract_id' => $contract->id,
                'service_id' => $service->id,
                'rate' => $rates['rate'],
                'rate_type' => $rates['rate_type'],
                'no_show_rate' => $rates['no_show_rate'] ?? 0,
                'no_show_rate_type' => $rates['no_show_rate_type'] ?? $rates['rate_type'],
            ]);
        }
    }
}
