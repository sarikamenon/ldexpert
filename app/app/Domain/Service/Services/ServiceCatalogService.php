<?php

declare(strict_types=1);

namespace App\Domain\Service\Services;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
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

    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

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

    public function metrics(): array
    {
        return $this->repository->metrics();
    }
}
