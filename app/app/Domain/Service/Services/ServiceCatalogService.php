<?php

declare(strict_types=1);

namespace App\Domain\Service\Services;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ServiceCatalogService
{
    public function __construct(
        private readonly ServiceRepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, Service> */
    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Support\Collection<int,Service>}
     */
    public function listForDataTables(ServiceFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return Collection<int, Service> */
    public function all(ServiceFilterDTO $filters): Collection
    {
        return $this->repository->all($filters);
    }

    public function create(CreateServiceDTO $dto): Service
    {
        return $this->repository->create($dto);
    }

    public function update(Service $service, UpdateServiceDTO $dto): Service
    {
        return $this->repository->update($service, $dto);
    }

    public function changeStatus(Service $service, ChangeServiceStatusDTO $dto): Service
    {
        return $this->repository->changeStatus($service, $dto);
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    /** @return Collection<int, Service> */
    public function listActiveForSelect(): Collection
    {
        return $this->repository->listActiveForSelect();
    }

    /** @return Collection<int, Service> */
    public function listActiveWithFrequencyFlag(): Collection
    {
        return $this->repository->listActiveWithFrequencyFlag();
    }

    /** @return Collection<int, Service> */
    public function listIndirectServices(): Collection
    {
        return $this->repository->listIndirectServices();
    }
}
