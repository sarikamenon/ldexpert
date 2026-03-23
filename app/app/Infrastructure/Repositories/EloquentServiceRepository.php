<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentServiceRepository implements ServiceRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Service> */
    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator
    {
        return $this->applyFilters(Service::query(), $filters)
            ->orderBy('name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Service> */
    public function all(ServiceFilterDTO $filters): Collection
    {
        return $this->applyFilters(Service::query(), $filters)
            ->orderBy('name')
            ->get();
    }

    public function listForDataTables(ServiceFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(Service::query(), $filters);

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('services.id');

        if ($params->searchValue) {
            $baseQuery->where('name', 'like', '%'.$params->searchValue.'%');
        }

        $recordsFiltered = (clone $baseQuery)->count('services.id');

        $orderColumn = $params->orderColumn ?? 'services.name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';

        $baseQuery->orderBy($orderColumn, $orderDir);

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

    public function create(CreateServiceDTO $dto): Service
    {
        return Service::create($dto->toArray());
    }

    public function update(Service $service, UpdateServiceDTO $dto): Service
    {
        $service->update($dto->toArray());

        /** @var Service $freshService */
        $freshService = $service->fresh();

        return $freshService;
    }

    public function changeStatus(Service $service, ChangeServiceStatusDTO $dto): Service
    {
        $service->update($dto->toArray());

        /** @var Service $freshService */
        $freshService = $service->fresh();

        return $freshService;
    }

    public function metrics(): array
    {
        $total = Service::count();
        $active = Service::where('status', ServiceStatus::ACTIVE)->count();
        $inactive = Service::where('status', ServiceStatus::INACTIVE)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    private function applyFilters(Builder $query, ServiceFilterDTO $filters): Builder
    {
        if ($filters->search) {
            $query->where('name', 'like', '%'.$filters->search.'%');
        }

        if ($filters->status) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->isFrequencyService !== null) {
            $query->where('is_frequency_service', $filters->isFrequencyService);
        }

        if ($filters->isDirectService !== null) {
            $query->where('is_direct_service', $filters->isDirectService);
        }

        if ($filters->isGroupService !== null) {
            $query->where('is_group_service', $filters->isGroupService);
        }

        if ($filters->billable !== null) {
            $query->where('is_billable', $filters->billable);
        }

        return $query;
    }

    /** @return Collection<int, Service> */
    public function listActiveForSelect(): Collection
    {
        return Service::query()
            ->where('status', ServiceStatus::ACTIVE)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Service> */
    public function listActiveWithFrequencyFlag(): Collection
    {
        return Service::query()
            ->where('status', ServiceStatus::ACTIVE)
            ->select(['id', 'name', 'is_frequency_service'])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Service> */
    public function listIndirectServices(): Collection
    {
        return Service::query()
            ->where('status', ServiceStatus::ACTIVE)
            ->where('is_direct_service', false)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $commonServiceIds  Pre-computed intersection of therapist & school contract service IDs
     * @return Collection<int, Service>
     */
    public function listCommonIndirectServices(Collection $commonServiceIds): Collection
    {
        if ($commonServiceIds->isEmpty()) {
            return collect();
        }

        return Service::query()
            ->where('status', ServiceStatus::ACTIVE)
            ->where('is_direct_service', false)
            ->whereIn('id', $commonServiceIds)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    public function findOrFail(int $id): Service
    {
        return Service::findOrFail($id);
    }
}
