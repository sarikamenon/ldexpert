<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\SSAReport\UtilizationReportFilterDTO;
use Illuminate\Support\Collection;

final class SSAUtilizationReportService
{
    public function __construct(
        private readonly SSARepositoryInterface $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getReportData(UtilizationReportFilterDTO $filters): array
    {
        $ssas = $this->repository->getUtilizationReport($filters);

        $summary = $this->calculateSummary($ssas->items());

        return [
            'ssas' => $ssas,
            'summary' => $summary,
        ];
    }

    /** @return Collection<int, \App\Models\ServiceSupportAgreement> */
    public function export(UtilizationReportFilterDTO $filters): Collection
    {
        // Get all records without pagination for export
        $filters = new UtilizationReportFilterDTO(
            startDate: $filters->startDate,
            endDate: $filters->endDate,
            schoolIds: $filters->schoolIds,
            therapistIds: $filters->therapistIds,
            serviceIds: $filters->serviceIds,
            statuses: $filters->statuses,
            utilizationBand: $filters->utilizationBand,
            gradeLevel: $filters->gradeLevel,
            perPage: 10000, // Large number to get all records
        );

        $paginator = $this->repository->getUtilizationReport($filters);

        return Collection::make($paginator->items());
    }

    /**
     * @param  array<int, \App\Models\ServiceSupportAgreement>  $ssas
     * @return array<string, mixed>
     */
    private function calculateSummary(array $ssas): array
    {
        $totalTho = 0;
        $totalServed = 0;
        $underServed = 0;
        $onTarget = 0;
        $overServed = 0;

        foreach ($ssas as $ssa) {
            $tho = $ssa->tho_minutes ?? 0;
            $served = $ssa->served_minutes ?? 0;
            $totalTho += $tho;
            $totalServed += $served;

            if ($tho > 0) {
                $utilization = ($served / $tho) * 100;

                if ($utilization < 80) {
                    $underServed++;
                } elseif ($utilization <= 120) {
                    $onTarget++;
                } else {
                    $overServed++;
                }
            }
        }

        $overallUtilization = $totalTho > 0 ? ($totalServed / $totalTho) * 100 : 0;

        return [
            'total_tho_minutes' => $totalTho,
            'total_served_minutes' => $totalServed,
            'overall_utilization_percent' => round($overallUtilization, 2),
            'under_served_count' => $underServed,
            'on_target_count' => $onTarget,
            'over_served_count' => $overServed,
        ];
    }
}
