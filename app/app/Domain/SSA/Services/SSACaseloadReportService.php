<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\SSAReport\CaseloadReportFilterDTO;
use App\Enums\ServiceFrequency;
use Illuminate\Support\Collection;

final class SSACaseloadReportService
{
    public function __construct(
        private readonly SSARepositoryInterface $repository,
    ) {}

    public function getReportData(CaseloadReportFilterDTO $filters): array
    {
        $ssas = $this->repository->getCaseloadReport($filters);

        $therapistData = $this->groupByTherapist($ssas);
        $summary = $this->calculateSummary($therapistData);

        return [
            'therapistData' => $therapistData,
            'unassignedSSAs' => $ssas->whereNull('assigned_therapist_id'),
            'summary' => $summary,
        ];
    }

    public function export(CaseloadReportFilterDTO $filters): Collection
    {
        return $this->repository->getCaseloadReport($filters);
    }

    private function groupByTherapist(Collection $ssas): Collection
    {
        return $ssas
            ->whereNotNull('assigned_therapist_id')
            ->groupBy('assigned_therapist_id')
            ->map(function (Collection $therapistSSAs, int $therapistId) {
                $therapist = $therapistSSAs->first()->assignedTherapist;
                $schools = $therapistSSAs->map(function ($ssa) {
                    return $ssa->student?->studentProfile?->school;
                })
                    ->filter()
                    ->unique('id')
                    ->values();

                $minutesPerWeek = $therapistSSAs->sum(function ($ssa) {
                    return $this->calculateMinutesPerWeek($ssa);
                });

                return [
                    'therapist' => $therapist,
                    'therapist_id' => $therapistId,
                    'schools' => $schools,
                    'active_ssa_count' => $therapistSSAs->count(),
                    'authorized_minutes_per_week' => round($minutesPerWeek, 2),
                    'ssas' => $therapistSSAs,
                ];
            })
            ->values();
    }

    private function calculateSummary(Collection $therapistData): array
    {
        $totalTherapists = $therapistData->count();
        $totalSSAs = $therapistData->sum('active_ssa_count');
        $minutesPerWeek = $therapistData->pluck('authorized_minutes_per_week')->filter();

        $median = $minutesPerWeek->count() > 0
            ? $minutesPerWeek->sort()->values()->get((int) floor($minutesPerWeek->count() / 2))
            : 0;

        $percentile90 = $minutesPerWeek->count() > 0
            ? $minutesPerWeek->sort()->values()->get((int) floor($minutesPerWeek->count() * 0.9))
            : 0;

        return [
            'total_therapists' => $totalTherapists,
            'total_active_ssas' => $totalSSAs,
            'median_minutes_per_week' => round($median, 2),
            'percentile_90_minutes_per_week' => round($percentile90, 2),
        ];
    }

    private function calculateMinutesPerWeek($ssa): float
    {
        if (! $ssa->frequency || ! $ssa->sessions_per_frequency) {
            return 0;
        }

        $weeksPerFrequency = match ($ssa->frequency) {
            ServiceFrequency::WEEKLY => 1,
            ServiceFrequency::BI_WEEKLY => 2,
            ServiceFrequency::MONTHLY => 4.33,
            ServiceFrequency::QUARTERLY => 13,
        };

        return ($ssa->minutes_per_session * $ssa->sessions_per_frequency) / $weeksPerFrequency;
    }
}
