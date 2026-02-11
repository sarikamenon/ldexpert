<?php

declare(strict_types=1);

namespace App\Domain\Service\Repositories;

use App\DTOs\ChangeServiceStatusDTO;
use App\DTOs\CreateServiceDTO;
use App\DTOs\ServiceFilterDTO;
use App\DTOs\UpdateServiceDTO;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceRepositoryInterface
{
    public function paginate(ServiceFilterDTO $filters): LengthAwarePaginator;

    public function all(ServiceFilterDTO $filters): Collection;

    public function create(CreateServiceDTO $dto): Service;

    public function update(Service $service, UpdateServiceDTO $dto): Service;

    public function changeStatus(Service $service, ChangeServiceStatusDTO $dto): Service;

    /**
     * @return array{total:int,active:int,inactive:int}
     */
    public function metrics(): array;

    public function listActiveForSelect(): Collection;

    public function listActiveWithFrequencyFlag(): Collection;

    public function listIndirectServices(): Collection;

    public function findOrFail(int $id): Service;
}
