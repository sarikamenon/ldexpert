<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\ServiceAlias\Repositories\ServiceAliasRepositoryInterface;
use App\DTOs\CreateServiceAliasDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceAliasFilterDTO;
use App\DTOs\UpdateServiceAliasDTO;
use App\Enums\SSAImportType;
use App\Models\Service;
use App\Models\ServiceAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentServiceAliasRepository implements ServiceAliasRepositoryInterface
{
    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:Collection<int,ServiceAlias>}
     */
    public function listForDataTables(ServiceAliasFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(ServiceAlias::query()->with('service'), $filters);

        $recordsTotal = ServiceAlias::count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumn = $params->orderColumn ?? 'service_aliases.source';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);
        if ($params->orderColumn === null) {
            $baseQuery->orderBy('service_aliases.external_name');
        }

        $rows = $baseQuery
            ->offset($params->start)
            ->limit($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function create(CreateServiceAliasDTO $dto): ServiceAlias
    {
        return ServiceAlias::create($dto->toArray());
    }

    public function update(ServiceAlias $serviceAlias, UpdateServiceAliasDTO $dto): ServiceAlias
    {
        $serviceAlias->update($dto->toArray());

        /** @var ServiceAlias $fresh */
        $fresh = $serviceAlias->fresh();

        return $fresh;
    }

    public function delete(ServiceAlias $serviceAlias): bool
    {
        return (bool) $serviceAlias->delete();
    }

    /** @return array<string, int> */
    public function getMetrics(): array
    {
        $metrics = ['total' => ServiceAlias::count()];

        foreach (SSAImportType::aliasSources() as $source) {
            $metrics[strtolower($source->value)] = ServiceAlias::query()->forSource($source->value)->count();
        }

        return $metrics;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Service> */
    public function listActiveServicesForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Service::query()->active()->orderBy('name')->get();
    }

    /**
     * @param  Builder<ServiceAlias>  $query
     * @return Builder<ServiceAlias>
     */
    private function applyFilters(Builder $query, ServiceAliasFilterDTO $filters): Builder
    {
        if ($filters->source !== null) {
            $query->forSource($filters->source);
        }

        $query->search($filters->search);

        return $query;
    }
}
