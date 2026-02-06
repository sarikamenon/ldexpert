<?php

declare(strict_types=1);

namespace App\Domain\School\Repositories;

use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SchoolRepositoryInterface
{
    public function paginate(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator;

    public function create(CreateSchoolDTO $dto): School;

    public function update(School $school, UpdateSchoolDTO $dto): School;

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School;

    /**
     * @return array{total:int,active:int,inactive:int}
     */
    public function metrics(): array;

    public function export(SchoolFilterDTO $filters): Collection;

    public function listAllForSelect(): Collection;

    public function listActiveForSelect(): Collection;

    public function find(int $id): ?School;

    public function findByExternalEmrName(string $externalEmrName): ?School;
}
