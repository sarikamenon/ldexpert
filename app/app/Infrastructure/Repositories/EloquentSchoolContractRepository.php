<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\DTOs\ContractServiceRateDTO;
use App\DTOs\CreateSchoolContractDTO;
use App\DTOs\SchoolContractFilterDTO;
use App\DTOs\UpdateSchoolContractDTO;
use App\Enums\ContractStatus;
use App\Models\SchoolContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentSchoolContractRepository implements SchoolContractRepositoryInterface
{
    public function paginate(SchoolContractFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderByDesc('start_date')
            ->paginate($perPage);
    }

    public function create(CreateSchoolContractDTO $dto): SchoolContract
    {
        return SchoolContract::create([
            'school_id' => $dto->schoolId,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'notes' => $dto->notes,
            'status' => $dto->status->value,
        ]);
    }

    public function update(SchoolContract $contract, UpdateSchoolContractDTO $dto): SchoolContract
    {
        $contract->update([
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'notes' => $dto->notes,
            'status' => $dto->status->value,
        ]);

        return $contract->refresh();
    }

    public function syncServices(SchoolContract $contract, array $services): void
    {
        $contract->services()->delete();
        $contract->services()->createMany(
            array_map(
                static fn (ContractServiceRateDTO $dto) => [
                    'service_id' => $dto->serviceId,
                    'rate' => $dto->rate,
                    'rate_type' => $dto->rateType->value,
                ],
                $services,
            )
        );
    }

    public function changeStatus(SchoolContract $contract, ContractStatus $status): SchoolContract
    {
        $contract->update([
            'status' => $status->value,
        ]);

        return $contract->refresh();
    }

    public function hasOverlap(int $schoolId, string $startDate, string $endDate, ?int $ignoreId = null): bool
    {
        return SchoolContract::query()
            ->where('school_id', $schoolId)
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
        $total = SchoolContract::count();
        $active = SchoolContract::where('status', ContractStatus::ACTIVE->value)->count();
        $inactive = SchoolContract::where('status', ContractStatus::INACTIVE->value)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function findActiveContractForDate(int $schoolId, string $date): ?SchoolContract
    {
        $dateObj = \Carbon\Carbon::parse($date);

        return SchoolContract::query()
            ->where('school_id', $schoolId)
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('start_date', '<=', $dateObj)
            ->whereDate('end_date', '>=', $dateObj)
            ->first();
    }

    public function getServiceRate(int $contractId, int $serviceId): ?array
    {
        $contractService = \App\Models\SchoolContractService::query()
            ->where('school_contract_id', $contractId)
            ->where('service_id', $serviceId)
            ->first();

        if (! $contractService) {
            return null;
        }

        return [
            'rate_type' => $contractService->rate_type,
            'rate_amount' => (float) $contractService->rate,
        ];
    }

    private function baseQuery(): Builder
    {
        return SchoolContract::query()
            ->with(['school', 'services.service']);
    }

    private function applyFilters(Builder $query, SchoolContractFilterDTO $filters): Builder
    {
        if ($filters->status instanceof ContractStatus) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->search) {
            $query->where(function (Builder $builder) use ($filters) {
                $builder->where('id', $filters->search)
                    ->orWhereHas('school', function (Builder $schoolQuery) use ($filters) {
                        $schoolQuery->where('full_name', 'like', '%'.$filters->search.'%')
                            ->orWhere('display_name', 'like', '%'.$filters->search.'%');
                    });
            });
        }

        if ($filters->schoolId) {
            $query->where('school_id', $filters->schoolId);
        }

        if (!empty($filters->schoolIds)) {
            $query->whereIn('school_id', $filters->schoolIds);
        }

        return $query;
    }
}
