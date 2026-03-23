<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Constants\ServiceCatalog;
use App\Enums\ContractStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsFixedSchoolYear;
use Illuminate\Database\Seeder;

final class ScenarioSchoolContractSeeder extends Seeder
{
    use SeedsFixedSchoolYear;

    /**
     * 10 schools get one full-year contract; 5 schools get two 6-month contracts.
     */
    public function run(): void
    {
        $schoolYear = $this->fixedSchoolYear2025();
        $catalog = ServiceCatalog::services();
        $catalogByName = [];
        foreach ($catalog as $service) {
            $catalogByName[$service['name']] = $service;
        }

        $serviceModels = Service::query()
            ->whereIn('name', array_keys($catalogByName))
            ->get()
            ->keyBy('name');

        $schools = School::query()->orderBy('id')->get();
        if ($schools->count() < 15) {
            $this->command?->warn('ScenarioSchoolContractSeeder: expected at least 15 schools.');

            return;
        }

        $fullYearStart = $schoolYear['start'];
        $fullYearEnd = $schoolYear['end'];
        $split1End = Carbon::create(2026, 1, 31)->endOfDay();
        $split2Start = Carbon::create(2026, 2, 1)->startOfDay();

        foreach ($schools->take(10) as $school) {
            $this->createContractWithServices($school, $fullYearStart, $fullYearEnd, $catalogByName, $serviceModels);
        }

        foreach ($schools->slice(10, 5) as $school) {
            $this->createContractWithServices($school, $fullYearStart, $split1End, $catalogByName, $serviceModels);
            $this->createContractWithServices($school, $split2Start, $fullYearEnd, $catalogByName, $serviceModels);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogByName
     * @param  \Illuminate\Support\Collection<string, Service>  $serviceModels
     */
    private function createContractWithServices(
        School $school,
        Carbon $start,
        Carbon $end,
        array $catalogByName,
        $serviceModels
    ): void {
        $contract = SchoolContract::query()->create([
            'school_id' => $school->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'notes' => 'Scenario 2025 school contract.',
        ]);

        foreach ($catalogByName as $serviceName => $definition) {
            $serviceModel = $serviceModels->get($serviceName);
            if (! $serviceModel) {
                continue;
            }

            SchoolContractService::query()->create([
                'school_contract_id' => $contract->id,
                'service_id' => $serviceModel->id,
                'rate' => $definition['rate'],
                'rate_type' => $definition['rate_type'],
                'no_show_rate' => $definition['no_show_rate'] ?? 0,
                'no_show_rate_type' => $definition['no_show_rate_type'] ?? $definition['rate_type'],
            ]);
        }
    }
}
