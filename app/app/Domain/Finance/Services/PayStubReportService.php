<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\IrsReportFilterDTO;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PayStubReportService
{
    public function __construct(
        private readonly IrsReportService $irsReportService,
    ) {}

    /**
     * Get unique therapists who have at least one payment in the given year.
     *
     * @return array<int, array{therapist_id: int, therapist_name: string, payment_count: int, total_amount: float}>
     */
    public function getTherapistsWithPayments(int $year): array
    {
        $results = TherapistBillPayment::query()
            ->select([
                'therapist_id',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(amount) as total_amount'),
            ])
            ->forYear($year)
            ->groupBy('therapist_id')
            ->get();

        $therapistIds = $results->pluck('therapist_id')->all();

        /** @var array<int, string> $therapists */
        $therapists = User::query()
            ->whereIn('id', $therapistIds)
            ->pluck('name', 'id')
            ->all();

        $rows = [];
        foreach ($results as $row) {
            $therapistId = (int) $row->getAttribute('therapist_id');
            $rows[] = [
                'therapist_id' => $therapistId,
                'therapist_name' => $therapists[$therapistId] ?? 'Unknown',
                'payment_count' => (int) $row->getAttribute('payment_count'),
                'total_amount' => round((float) $row->getAttribute('total_amount'), 2),
            ];
        }

        return $rows;
    }

    /**
     * Get all calendar years in which a therapist has at least one payment, descending.
     *
     * @return array<int, int>
     */
    public function getYearsWithPayments(int $therapistId): array
    {
        return TherapistBillPayment::query()
            ->selectRaw('YEAR(paid_at) as pay_year')
            ->forTherapist($therapistId)
            ->groupByRaw('YEAR(paid_at)')
            ->orderByRaw('YEAR(paid_at) DESC')
            ->pluck('pay_year') // @phpstan-ignore argument.type
            ->map(fn (mixed $y): int => (int) $y)
            ->all();
    }

    /**
     * Get full pay stub data for one therapist in one year.
     * Reuses IrsReportService for YTD calculations.
     *
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>, therapist_name: string, year: int}
     */
    public function getTherapistPayStubData(int $therapistId, int $year): array
    {
        $filters = IrsReportFilterDTO::fromArray([
            'date_from' => $year.'-01-01',
            'date_to' => $year.'-12-31',
            'therapist_ids' => [$therapistId],
        ]);

        $reportData = $this->irsReportService->getReportData($filters);

        /** @var User|null $therapist */
        $therapist = User::find($therapistId);
        $therapistName = $therapist !== null ? $therapist->name : 'Unknown';

        return [
            'rows' => $reportData['rows'],
            'summary' => $reportData['summary'],
            'therapist_name' => $therapistName,
            'year' => $year,
        ];
    }
}
