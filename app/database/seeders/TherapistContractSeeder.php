<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\ServiceCatalog;
use App\Enums\ContractStatus;
use App\Models\Service;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class TherapistContractSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = $this->currentSchoolYear();
        $catalog = ServiceCatalog::services();
        $servicesByName = Service::query()
            ->whereIn('name', array_column($catalog, 'name'))
            ->get()
            ->keyBy('name');

        TherapistProfile::query()->each(function (TherapistProfile $therapist) use ($catalog, $servicesByName, $schoolYear): void {
            $contract = $this->findOrCreateContract($therapist, $schoolYear['start'], $schoolYear['end']);

            // Add all services from catalog to each therapist contract
            foreach ($catalog as $serviceDefinition) {
                $serviceModel = $servicesByName->get($serviceDefinition['name']);

                if (! $serviceModel) {
                    continue;
                }

                TherapistContractService::query()->updateOrCreate(
                    [
                        'therapist_contract_id' => $contract->id,
                        'service_id' => $serviceModel->id,
                    ],
                    [
                        'rate' => $serviceDefinition['rate'],
                        'rate_type' => $serviceDefinition['rate_type'],
                    ]
                );
            }
        });
    }

    private function currentSchoolYear(): array
    {
        $today = now();
        $startYear = $today->month >= 7 ? $today->year : $today->year - 1;

        return [
            'start' => Carbon::create($startYear, 7, 1)->startOfDay(),
            'end' => Carbon::create($startYear + 1, 6, 30)->endOfDay(),
        ];
    }

    private function findOrCreateContract(TherapistProfile $therapist, Carbon $start, Carbon $end): TherapistContract
    {
        $existing = TherapistContract::query()
            ->where('therapist_id', $therapist->id)
            ->whereDate('start_date', '<=', $start)
            ->whereDate('end_date', '>=', $end)
            ->latest('start_date')
            ->first();

        if ($existing) {
            return $existing;
        }

        return TherapistContract::query()->create([
            'therapist_id' => $therapist->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => ContractStatus::ACTIVE->value,
            'notes' => 'Seeder generated annual therapist contract.',
        ]);
    }
}
