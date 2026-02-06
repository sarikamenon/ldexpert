<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function paginate(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderBy('display_name')
            ->paginate($perPage);
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

    public function export(SchoolFilterDTO $filters): Collection
    {
        return $this->applyFilters($this->baseQuery(), $filters)
            ->orderBy('display_name')
            ->get();
    }

    public function listAllForSelect(): Collection
    {
        return School::query()
            ->select(['id', 'display_name'])
            ->orderBy('display_name')
            ->get();
    }

    public function listActiveForSelect(): Collection
    {
        return School::query()
            ->active()
            ->select(['id', 'display_name'])
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

    private function baseQuery(): Builder
    {
        return School::query()->with('manager');
    }

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
