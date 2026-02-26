<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\SSAReport\ExpirationReportFilterDTO;
use Illuminate\Support\Collection;

final class SSAExpirationReportService
{
    public function __construct(
        private readonly SSARepositoryInterface $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getReportData(ExpirationReportFilterDTO $filters): array
    {
        $reportData = $this->repository->getExpirationReport($filters);

        $summary = $this->calculateSummary($reportData, $filters);

        return [
            'upcoming' => $reportData['upcoming'],
            'expired' => $reportData['expired'],
            'pending' => $reportData['pending'],
            'no_current' => $reportData['no_current'],
            'summary' => $summary,
        ];
    }

    /** @return Collection<int, \App\Models\ServiceSupportAgreement> */
    public function export(ExpirationReportFilterDTO $filters): Collection
    {
        $reportData = $this->repository->getExpirationReport($filters);

        $bucket = $filters->bucket;

        if ($bucket === 'upcoming') {
            return $reportData['upcoming'];
        }

        if ($bucket === 'expired') {
            return $reportData['expired'];
        }

        if ($bucket === 'pending') {
            return $reportData['pending'];
        }

        // Export all if no bucket specified
        return $reportData['upcoming']
            ->merge($reportData['expired'])
            ->merge($reportData['pending']);
    }

    /**
     * @param  array<string, mixed>  $reportData
     * @return array<string, mixed>
     */
    private function calculateSummary(array $reportData, ExpirationReportFilterDTO $filters): array
    {
        return [
            'upcoming_count' => $reportData['upcoming']->count(),
            'expired_count' => $reportData['expired']->count(),
            'pending_count' => $reportData['pending']->count(),
            'no_current_count' => $reportData['no_current']->count(),
            'expiration_window_days' => $filters->expirationWindowDays,
        ];
    }
}
