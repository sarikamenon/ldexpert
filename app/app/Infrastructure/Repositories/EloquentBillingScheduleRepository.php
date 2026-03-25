<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Billing\Repositories\BillingScheduleRepositoryInterface;
use App\DTOs\BillingScheduleFilterDTO;
use App\DTOs\DataTablesParamsDTO;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentBillingScheduleRepository implements BillingScheduleRepositoryInterface
{
    public function find(int $id): ?BillingSchedule
    {
        return BillingSchedule::with(['schedulable', 'latestRun'])->find($id);
    }

    /**
     * @return Collection<int, BillingSchedule>
     */
    public function getDueSchedules(string $type = 'all'): Collection
    {
        $query = BillingSchedule::query()
            ->due()
            ->with(['schedulable']);

        if ($type !== 'all') {
            $query->where('schedule_type', $type);
        }

        /** @var Collection<int, BillingSchedule> $result */
        $result = $query->get();

        return $result;
    }

    public function getForEntity(string $schedulableType, int $schedulableId, string $scheduleType): ?BillingSchedule
    {
        return BillingSchedule::query()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->where('schedule_type', $scheduleType)
            ->first();
    }

    public function findForEntityIncludingTrashed(string $schedulableType, int $schedulableId, string $scheduleType): ?BillingSchedule
    {
        return BillingSchedule::withTrashed()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->where('schedule_type', $scheduleType)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BillingSchedule
    {
        return BillingSchedule::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BillingSchedule $schedule, array $data): BillingSchedule
    {
        $schedule->update($data);
        $schedule->refresh();

        return $schedule;
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, BillingSchedule>}
     */
    public function listForDataTables(BillingScheduleFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(BillingSchedule::query()->with(['schedulable', 'latestRun']), $filters);
        $recordsTotal = (clone $baseQuery)->count('billing_schedules.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search): void {
                $q->where('schedule_type', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('billing_schedules.id');

        $orderColumn = $params->orderColumn ?? 'next_run_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, BillingSchedule> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logRun(array $data): BillingScheduleRun
    {
        return BillingScheduleRun::create($data);
    }

    /**
     * @return Collection<int, BillingScheduleRun>
     */
    public function getRunHistory(int $scheduleId, int $limit = 20): Collection
    {
        /** @var Collection<int, BillingScheduleRun> $result */
        $result = BillingScheduleRun::query()
            ->where('billing_schedule_id', $scheduleId)
            ->with(['invoice', 'therapistBill'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();

        return $result;
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, BillingScheduleRun>}
     */
    public function listRunsForDataTables(int $scheduleId, DataTablesParamsDTO $params): array
    {
        /** @var Builder<BillingScheduleRun> $baseQuery */
        $baseQuery = BillingScheduleRun::query()
            ->where('billing_schedule_id', $scheduleId)
            ->with(['invoice', 'therapistBill']);

        $recordsTotal = (clone $baseQuery)->count('billing_schedule_runs.id');

        if ($params->searchValue !== null) {
            $search = '%'.$params->searchValue.'%';
            $baseQuery->where(function (Builder $q) use ($search): void {
                $q->where('billing_schedule_runs.status', 'like', $search)
                    ->orWhere('billing_schedule_runs.error_message', 'like', $search)
                    ->orWhereHas('invoice', static function ($query) use ($search): void {
                        $query->where('invoice_number', 'like', $search); // @phpstan-ignore argument.type
                    })
                    ->orWhereHas('therapistBill', static function ($query) use ($search): void {
                        $query->where('bill_number', 'like', $search); // @phpstan-ignore argument.type
                    });
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('billing_schedule_runs.id');

        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        if ($params->orderColumn !== null) {
            $baseQuery->orderBy('billing_schedule_runs.'.$params->orderColumn, $orderDir);
        } else {
            $baseQuery->orderByDesc('billing_schedule_runs.started_at');
        }

        /** @var Collection<int, BillingScheduleRun> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function delete(BillingSchedule $schedule): bool
    {
        return (bool) $schedule->delete();
    }

    /**
     * @param  Builder<BillingSchedule>  $query
     * @return Builder<BillingSchedule>
     */
    private function applyFilters(Builder $query, BillingScheduleFilterDTO $filters): Builder
    {
        if ($filters->scheduleType !== null) {
            $query->where('schedule_type', $filters->scheduleType->value);
        }

        if ($filters->billingMode !== null) {
            $query->where('billing_mode', $filters->billingMode->value);
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        if ($filters->frequency !== null) {
            $query->where('frequency', $filters->frequency);
        }

        return $query;
    }
}
