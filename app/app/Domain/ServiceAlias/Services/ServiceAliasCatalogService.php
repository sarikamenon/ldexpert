<?php

declare(strict_types=1);

namespace App\Domain\ServiceAlias\Services;

use App\Domain\ServiceAlias\Repositories\ServiceAliasRepositoryInterface;
use App\DTOs\CreateServiceAliasDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceAliasFilterDTO;
use App\DTOs\UpdateServiceAliasDTO;
use App\Models\Service;
use App\Models\ServiceAlias;
use Illuminate\Support\Collection;

final class ServiceAliasCatalogService
{
    public function __construct(
        private readonly ServiceAliasRepositoryInterface $repository,
    ) {}

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:Collection<int,ServiceAlias>}
     */
    public function listForDataTables(ServiceAliasFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    public function create(CreateServiceAliasDTO $dto): ServiceAlias
    {
        return $this->repository->create($dto);
    }

    public function update(ServiceAlias $serviceAlias, UpdateServiceAliasDTO $dto): ServiceAlias
    {
        return $this->repository->update($serviceAlias, $dto);
    }

    public function delete(ServiceAlias $serviceAlias): bool
    {
        return $this->repository->delete($serviceAlias);
    }

    /** @return array<string, int> */
    public function getMetrics(): array
    {
        return $this->repository->getMetrics();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Service> */
    public function listActiveServicesForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->listActiveServicesForSelect();
    }
}
