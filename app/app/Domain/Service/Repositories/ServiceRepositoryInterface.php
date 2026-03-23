<?php

declare(strict_types=1);

namespace App\Domain\Service\Repositories;

use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Service> */
    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator;

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:Collection<int,Service>}
     */
    public function listForDataTables(ServiceFilterDTO $filters, DataTablesParamsDTO $params): array;

    /** @return Collection<int, Service> */
    public function all(ServiceFilterDTO $filters): Collection;

    public function create(CreateServiceDTO $dto): Service;

    public function update(Service $service, UpdateServiceDTO $dto): Service;

    public function changeStatus(Service $service, ChangeServiceStatusDTO $dto): Service;

    /**
     * @return array{total:int,active:int,inactive:int}
     */
    public function metrics(): array;

    /** @return Collection<int, Service> */
    public function listActiveForSelect(): Collection;

    /** @return Collection<int, Service> */
    public function listActiveWithFrequencyFlag(): Collection;

    /** @return Collection<int, Service> */
    public function listIndirectServices(): Collection;

    /** @return Collection<int, Service> */
    public function listCommonIndirectServices(int $therapistProfileId, int $schoolId): Collection;

    public function findOrFail(int $id): Service;
}
