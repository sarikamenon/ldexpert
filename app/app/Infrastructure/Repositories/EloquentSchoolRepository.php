<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    /** @return LengthAwarePaginator<int, School> */
    public function paginate(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderBy('display_name')
            ->paginate($perPage);
    }

    public function listForDataTables(SchoolFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters($this->baseQuery(), $filters)
            ->leftJoin('users', 'schools.manager_id', '=', 'users.id')
            ->select('schools.*');

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('schools.id');

        if ($params->searchValue) {
            $baseQuery->search($params->searchValue);
        }

        $recordsFiltered = (clone $baseQuery)->count('schools.id');

        $orderColumn = $params->orderColumn ?? 'schools.display_name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';

        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function create(CreateSchoolDTO $dto): School
    {
        return School::create($dto->toArray());
    }

    public function update(School $school, UpdateSchoolDTO $dto): School
    {
        $school->update($dto->toArray());

        return $school->refresh();
    }

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School
    {
        $school->update([
            'status' => $dto->status->value,
            'status_reason' => $dto->reason,
        ]);

        return $school->refresh();
    }

    public function metrics(): array
    {
        $total = School::count();
        $active = School::where('status', SchoolStatus::ACTIVE->value)->count();
        $inactive = School::where('status', SchoolStatus::INACTIVE->value)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /** @return Collection<int, School> */
    public function export(SchoolFilterDTO $filters): Collection
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderBy('display_name')
            ->get();
    }

    /** @return Collection<int, School> */
    public function listAllForSelect(): Collection
    {
        return School::query()
            ->select(['id', 'display_name', 'is_private_student'])
            ->orderBy('display_name')
            ->get();
    }

    /** @return Collection<int, string|null> map of school id => timezone */
    public function listSchoolTimezones(): Collection
    {
        return School::query()
            ->pluck('timezone', 'id');
    }

    /** @return Collection<int, School> */
    public function listPrivateFamilyContacts(): Collection
    {
        return School::query()
            ->where('is_private_student', true)
            ->select([
                'id',
                'contact_first_name',
                'contact_last_name',
                'contact_email',
                'contact_phone',
                'timezone',
            ])
            ->orderBy('display_name')
            ->get();
    }

    /** @return Collection<int, School> */
    public function listActiveForSelect(): Collection
    {
        return School::query()
            ->active()
            ->select(['id', 'display_name', 'is_private_student'])
            ->orderBy('display_name')
            ->get();
    }

    public function find(int $id): ?School
    {
        return School::find($id);
    }

    public function findByExternalEmrName(string $externalEmrName): ?School
    {
        return School::query()
            ->where('external_emr_name', $externalEmrName)
            ->first();
    }

    /** @return Builder<School> */
    private function baseQuery(): Builder
    {
        return School::query()->with('manager');
    }

    /**
     * @param  Builder<School>  $query
     * @return Builder<School>
     */
    private function applyFilters(Builder $query, SchoolFilterDTO $filters): Builder
    {
        $query->search($filters->search);

        if ($filters->status instanceof SchoolStatus) {
            $query->status($filters->status);
        } elseif (! $filters->includeDeactivated) {
            $query->active();
        }

        return $query;
    }
}
