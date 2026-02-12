<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\ServiceCatalog;
use Database\Seeders\Concerns\SeedsSchoolYear;
use App\Enums\ContractStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class SchoolContractSeeder extends Seeder
{
    use SeedsSchoolYear;

    public function run(): void
    {
        $schoolYear = $this->currentSchoolYear();
        $catalog = ServiceCatalog::services();
        $catalogByName = [];

        foreach ($catalog as $service) {
            $catalogByName[$service['name']] = $service;
        }

        $serviceModels = Service::query()
            ->whereIn('name', array_keys($catalogByName))
            ->get()
            ->keyBy('name');

        School::query()->each(function (School $school) use ($catalogByName, $schoolYear, $serviceModels): void {
            $contract = $this->findOrCreateContract($school, $schoolYear['start'], $schoolYear['end']);

            // Add all services from catalog to each school contract
            foreach ($catalogByName as $serviceName => $definition) {
                $serviceModel = $serviceModels->get($serviceName);

                if (! $serviceModel) {
                    continue;
                }

                SchoolContractService::query()->updateOrCreate(
                    [
                        'school_contract_id' => $contract->id,
                        'service_id' => $serviceModel->id,
                    ],
                    [
                        'rate' => $definition['rate'],
                        'rate_type' => $definition['rate_type'],
                        'no_show_rate' => $definition['no_show_rate'] ?? 0,
                        'no_show_rate_type' => $definition['no_show_rate_type'] ?? $definition['rate_type'],
                    ]
                );
            }
        });
    }

    private function findOrCreateContract(School $school, Carbon $start, Carbon $end): SchoolContract
    {
        $existing = SchoolContract::query()
            ->where('school_id', $school->id)
            ->whereDate('start_date', '<=', $start)
            ->whereDate('end_date', '>=', $end)
            ->latest('start_date')
            ->first();

        if ($existing) {
            return $existing;
        }

        return SchoolContract::query()->create([
            'school_id' => $school->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'notes' => 'Seeder generated annual school contract.',
        ]);
    }
}
