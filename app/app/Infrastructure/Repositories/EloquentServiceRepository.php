<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentServiceRepository implements ServiceRepositoryInterface
{
    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator
    {
        return $this->applyFilters(Service::query(), $filters)
            ->orderBy('name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    public function all(ServiceFilterDTO $filters): Collection
    {
        return $this->applyFilters(Service::query(), $filters)
            ->orderBy('name')
            ->get();
    }

    public function create(CreateServiceDTO $dto): Service
    {
        return Service::create($dto->toArray());
    }

    public function update(Service $service, UpdateServiceDTO $dto): Service
    {
        $service->update($dto->toArray());

        return $service->fresh();
    }

    public function changeStatus(Service $service, ChangeServiceStatusDTO $dto): Service
    {
        $service->update($dto->toArray());

        return $service->fresh();
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
}
