<?php

declare(strict_types=1);

namespace App\Domain\ServiceAlias\Repositories;

use App\DTOs\CreateServiceAliasDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceAliasFilterDTO;
use App\DTOs\UpdateServiceAliasDTO;
use App\Models\ServiceAlias;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ServiceAliasRepositoryInterface
{
    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:Collection<int,ServiceAlias>}
     */
    public function listForDataTables(ServiceAliasFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function create(CreateServiceAliasDTO $dto): ServiceAlias;

    public function update(ServiceAlias $serviceAlias, UpdateServiceAliasDTO $dto): ServiceAlias;

    public function delete(ServiceAlias $serviceAlias): bool;

    /**
     * @return array<string, int>
     */
    public function getMetrics(): array;

    /** @return EloquentCollection<int, \App\Models\Service> */
    public function listActiveServicesForSelect(): EloquentCollection;
}
