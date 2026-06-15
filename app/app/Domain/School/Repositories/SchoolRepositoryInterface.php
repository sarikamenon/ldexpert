<?php

declare(strict_types=1);

namespace App\Domain\School\Repositories;

use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface SchoolRepositoryInterface
{
    /** @return LengthAwarePaginator<int, School> */
    public function paginate(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator;

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:EloquentCollection<int,School>}
     */
    public function listForDataTables(SchoolFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function create(CreateSchoolDTO $dto): School;

    public function update(School $school, UpdateSchoolDTO $dto): School;

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School;

    /**
     * @return array{total:int,active:int,inactive:int}
     */
    public function metrics(): array;

    /** @return Collection<int, School> */
    public function export(SchoolFilterDTO $filters): Collection;

    /** @return Collection<int, School> */
    public function listAllForSelect(): Collection;

    /** @return Collection<int, School> */
    public function listPrivateFamilyContacts(): Collection;

    /** @return Collection<int, School> */
    public function listActiveForSelect(): Collection;

    public function find(int $id): ?School;

    public function findByExternalEmrName(string $externalEmrName): ?School;
}
