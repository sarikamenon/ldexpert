<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\DTOs\ContractServiceRateDTO;
use App\DTOs\CreateTherapistContractDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\UpdateTherapistContractDTO;
use App\Enums\ContractStatus;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentTherapistContractRepository implements TherapistContractRepositoryInterface
{
    public function paginate(TherapistContractFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderByDesc('start_date')
            ->paginate($perPage);
    }

    public function listForDataTables(TherapistContractFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters($this->baseQuery(), $filters);

        $recordsTotal = (clone $baseQuery)->count('therapist_contracts.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('therapist_contracts.id', 'like', '%'.$search.'%')
                    ->orWhereHas('therapist', function (Builder $tq) use ($search) {
                        $tq->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('therapist_contracts.id');

        $orderColumn = $params->orderColumn ?? 'therapist_contracts.start_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, TherapistContract> $rows */
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

    public function create(CreateTherapistContractDTO $dto): TherapistContract
    {
        return TherapistContract::create([
            'therapist_id' => $dto->therapistId,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'notes' => $dto->notes,
            'status' => $dto->status->value,
        ]);
    }

    public function update(TherapistContract $contract, UpdateTherapistContractDTO $dto): TherapistContract
    {
        $contract->update([
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'notes' => $dto->notes,
            'status' => $dto->status->value,
        ]);

        return $contract->refresh();
    }

    public function syncServices(TherapistContract $contract, array $services): void
    {
        $contract->services()->delete();
        $contract->services()->createMany(
            array_map(
                static fn (ContractServiceRateDTO $dto) => [
                    'service_id' => $dto->serviceId,
                    'rate' => $dto->rate,
                    'rate_type' => $dto->rateType->value,
                    'no_show_rate' => $dto->noShowRate,
                    'no_show_rate_type' => $dto->noShowRateType->value,
                ],
                $services,
            )
        );
    }

    public function changeStatus(TherapistContract $contract, ContractStatus $status): TherapistContract
    {
        $contract->update([
            'status' => $status->value,
        ]);

        return $contract->refresh();
    }

    public function hasOverlap(int $therapistId, string $startDate, string $endDate, ?int $ignoreId = null): bool
    {
        return TherapistContract::query()
            ->where('therapist_id', $therapistId)
            ->where('status', ContractStatus::ACTIVE->value)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where(function (Builder $query) use ($startDate, $endDate) {
                $query->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate);
            })
            ->exists();
    }

    public function metrics(): array
    {
        $total = TherapistContract::count();
        $active = TherapistContract::where('status', ContractStatus::ACTIVE->value)->count();
        $inactive = TherapistContract::where('status', ContractStatus::INACTIVE->value)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function findActiveContractForDate(int $therapistId, string $date): ?TherapistContract
    {
        $dateObj = \Carbon\Carbon::parse($date);

        return TherapistContract::query()
            ->where('therapist_id', $therapistId)
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('start_date', '<=', $dateObj)
            ->whereDate('end_date', '>=', $dateObj)
            ->first();
    }

    public function getServiceRate(int $contractId, int $serviceId): ?array
    {
        $contractService = TherapistContractService::query()
            ->where('therapist_contract_id', $contractId)
            ->where('service_id', $serviceId)
            ->first();

        if (! $contractService) {
            return null;
        }

        $noShowRate = (float) $contractService->no_show_rate;
        $noShowRateType = $contractService->no_show_rate_type ?? null;

        return [
            'rate_type' => $contractService->rate_type,
            'rate_amount' => (float) $contractService->rate,
            'no_show_rate' => $noShowRate,
            'no_show_rate_type' => $noShowRateType,
        ];
    }

    /** @return Builder<TherapistContract> */
    private function baseQuery(): Builder
    {
        return TherapistContract::query()
            ->with(['therapist.user', 'services.service']);
    }

    /**
     * @param Builder<TherapistContract> $query
     * @return Builder<TherapistContract>
     */
    private function applyFilters(Builder $query, TherapistContractFilterDTO $filters): Builder
    {
        if ($filters->status instanceof ContractStatus) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->search) {
            $query->where(function (Builder $builder) use ($filters) {
                $builder->where('id', $filters->search)
                    ->orWhereHas('therapist', function (Builder $therapistQuery) use ($filters) {
                        $therapistQuery
                            ->where('first_name', 'like', '%'.$filters->search.'%')
                            ->orWhere('last_name', 'like', '%'.$filters->search.'%');
                    });
            });
        }

        if ($filters->therapistId) {
            $query->where('therapist_id', $filters->therapistId);
        }

        if (! empty($filters->therapistIds)) {
            $query->whereIn('therapist_id', $filters->therapistIds);
        }

        return $query;
    }
}
