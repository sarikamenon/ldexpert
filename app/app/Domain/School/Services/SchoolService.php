<?php

declare(strict_types=1);

namespace App\Domain\School\Services;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchoolService
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schools,
    ) {}

    /** @return LengthAwarePaginator<int, School> */
    public function listSchools(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->schools->paginate($filters, $perPage);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Database\Eloquent\Collection<int,School>}
     */
    public function listForDataTables(SchoolFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->schools->listForDataTables($filters, $params);
    }

    public function createSchool(CreateSchoolDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($dto) {
            $school = $this->schools->create($dto);

            return $school;
        });
    }

    public function updateSchool(School $school, UpdateSchoolDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($school, $dto) {
            $updatedSchool = $this->schools->update($school, $dto);

            return $updatedSchool;
        });
    }

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School
    {
        /** @var School */
        return $this->wrapWrite(function () use ($school, $dto) {
            $updatedSchool = $this->schools->changeStatus($school, $dto);

            return $updatedSchool;
        });
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function summaryMetrics(): array
    {
        return $this->schools->metrics();
    }

    /** @return Collection<int, School> */
    public function exportSchools(SchoolFilterDTO $filters): Collection
    {
        return $this->schools->export($filters);
    }

    /** @return Collection<int, School> */
    public function listActiveForSelect(): Collection
    {
        return $this->schools->listActiveForSelect();
    }

    private function wrapWrite(callable $callback): mixed
    {
        try {
            return DB::transaction(static fn () => $callback());
        } catch (Throwable $exception) {
            Log::error('School write operation failed', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
