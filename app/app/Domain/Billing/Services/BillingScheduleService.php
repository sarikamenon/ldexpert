<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Repositories\BillingScheduleRepositoryInterface;
use App\DTOs\BillingScheduleDTO;
use App\DTOs\BillingScheduleFilterDTO;
use App\DTOs\DataTablesParamsDTO;
use App\Enums\BillingFrequency;
use App\Enums\GenerationDayType;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BillingScheduleService
{
    public function __construct(
        private readonly BillingScheduleRepositoryInterface $repository,
    ) {}

    public function find(int $id): ?BillingSchedule
    {
        return $this->repository->find($id);
    }

    public function getEntityConfig(string $schedulableType, int $schedulableId, string $scheduleType): ?BillingSchedule
    {
        return $this->repository->getForEntity($schedulableType, $schedulableId, $scheduleType);
    }

    /**
     * Create or update entity billing; restores a soft-deleted schedule when the user saves again
     * after "remove custom configuration" (unique key still holds the old row).
     *
     * @return array{schedule: BillingSchedule, created: bool}
     */
    public function upsertEntitySchedule(BillingScheduleDTO $dto): array
    {
        $existing = $this->repository->findForEntityIncludingTrashed(
            $dto->schedulableType,
            $dto->schedulableId,
            $dto->scheduleType,
        );

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return [
                'schedule' => $this->updateSchedule($existing, $dto),
                'created' => false,
            ];
        }

        return [
            'schedule' => $this->createSchedule($dto),
            'created' => true,
        ];
    }

    public function deleteSchedule(BillingSchedule $schedule): bool
    {
        return $this->repository->delete($schedule);
    }

    public function createSchedule(BillingScheduleDTO $dto): BillingSchedule
    {
        $data = $dto->toArray();

        $periodEnd = $this->determineCurrentPeriodEnd(
            BillingFrequency::from($dto->frequency),
            now(),
        );

        $data['next_run_at'] = $this->calculateNextRunDate(
            GenerationDayType::from($dto->generationDayType),
            $dto->generationDayOfWeek,
            $dto->generationDelayDays,
            $dto->minGraceDays,
            $periodEnd,
        )->toDateString();

        return $this->repository->create($data);
    }

    public function updateSchedule(BillingSchedule $schedule, BillingScheduleDTO $dto): BillingSchedule
    {
        $data = $dto->toArray();

        if ($schedule->last_period_end !== null) {
            $periodEnd = $this->determineNextPeriodEnd(
                BillingFrequency::from($dto->frequency),
                $schedule->last_period_end,
            );
        } else {
            $periodEnd = $this->determineCurrentPeriodEnd(
                BillingFrequency::from($dto->frequency),
                now(),
            );
        }

        $data['next_run_at'] = $this->calculateNextRunDate(
            GenerationDayType::from($dto->generationDayType),
            $dto->generationDayOfWeek,
            $dto->generationDelayDays,
            $dto->minGraceDays,
            $periodEnd,
        )->toDateString();

        return $this->repository->update($schedule, $data);
    }

    public function toggleActive(BillingSchedule $schedule): BillingSchedule
    {
        return $this->repository->update($schedule, [
            'is_active' => ! $schedule->is_active,
        ]);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, BillingSchedule>}
     */
    public function listForDataTables(BillingScheduleFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /**
     * @return Collection<int, BillingScheduleRun>
     */
    public function getRunHistory(int $scheduleId, int $limit = 20): Collection
    {
        return $this->repository->getRunHistory($scheduleId, $limit);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, BillingScheduleRun>}
     */
    public function listRunsForDataTables(int $scheduleId, DataTablesParamsDTO $params): array
    {
        return $this->repository->listRunsForDataTables($scheduleId, $params);
    }

    /**
     * Calculate the next run date for a billing schedule after a given period end.
     */
    public function calculateNextRunDate(
        GenerationDayType $generationDayType,
        ?int $generationDayOfWeek,
        ?int $generationDelayDays,
        int $minGraceDays,
        Carbon $periodEnd,
    ): Carbon {
        $earliest = $periodEnd->copy()->addDays($minGraceDays);

        if ($generationDayType === GenerationDayType::FIXED_DELAY) {
            $delayDate = $periodEnd->copy()->addDays($generationDelayDays ?? 3);

            return $delayDate->greaterThan($earliest) ? $delayDate : $earliest;
        }

        $target = $earliest->copy();
        $dayOfWeek = $generationDayOfWeek ?? 2; // Default to Tuesday

        while ((int) $target->dayOfWeek !== $dayOfWeek) {
            $target->addDay();
        }

        return $target;
    }

    /**
     * Determine the billing period boundaries for a given date.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function determineBillingPeriod(BillingFrequency $frequency, Carbon $referenceDate): array
    {
        return match ($frequency) {
            BillingFrequency::SEMI_MONTHLY => $this->semiMonthlyPeriod($referenceDate),
            BillingFrequency::MONTHLY => $this->monthlyPeriod($referenceDate),
            BillingFrequency::WEEKLY => $this->weeklyPeriod($referenceDate),
            BillingFrequency::BI_WEEKLY => $this->biWeeklyPeriod($referenceDate),
        };
    }

    /**
     * Get the end date of the current billing period containing the reference date.
     */
    public function determineCurrentPeriodEnd(BillingFrequency $frequency, Carbon $referenceDate): Carbon
    {
        return $this->determineBillingPeriod($frequency, $referenceDate)['end'];
    }

    /**
     * Get the end date of the next billing period after a given period end.
     */
    public function determineNextPeriodEnd(BillingFrequency $frequency, Carbon $lastPeriodEnd): Carbon
    {
        $nextDay = $lastPeriodEnd->copy()->addDay();

        return $this->determineBillingPeriod($frequency, $nextDay)['end'];
    }

    /**
     * Advance the schedule's tracking fields after a run.
     */
    public function advanceSchedule(BillingSchedule $schedule, Carbon $periodEnd): BillingSchedule
    {
        $nextPeriodEnd = $this->determineNextPeriodEnd($schedule->frequency, $periodEnd);

        $nextRunAt = $this->calculateNextRunDate(
            $schedule->generation_day_type,
            $schedule->generation_day_of_week ? (int) $schedule->generation_day_of_week : null,
            $schedule->generation_delay_days ? (int) $schedule->generation_delay_days : null,
            (int) $schedule->min_grace_days,
            $nextPeriodEnd,
        );

        return $this->repository->update($schedule, [
            'last_run_at' => now(),
            'last_period_end' => $periodEnd->toDateString(),
            'next_run_at' => $nextRunAt->toDateString(),
        ]);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function semiMonthlyPeriod(Carbon $date): array
    {
        if ($date->day <= 15) {
            return [
                'start' => $date->copy()->startOfMonth(),
                'end' => $date->copy()->setDay(15)->startOfDay(),
            ];
        }

        return [
            'start' => $date->copy()->setDay(16)->startOfDay(),
            'end' => $date->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function monthlyPeriod(Carbon $date): array
    {
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function weeklyPeriod(Carbon $date): array
    {
        return [
            'start' => $date->copy()->startOfWeek(Carbon::MONDAY),
            'end' => $date->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function biWeeklyPeriod(Carbon $date): array
    {
        $epoch = Carbon::parse('2026-01-05'); // A Monday as epoch for bi-weekly alignment
        $daysDiff = $epoch->diffInDays($date->copy()->startOfWeek(Carbon::MONDAY));
        $weekNumber = (int) floor($daysDiff / 7);
        $biWeekIndex = (int) floor($weekNumber / 2);

        $start = $epoch->copy()->addWeeks($biWeekIndex * 2);
        $end = $start->copy()->addDays(13);

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
