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
use App\Services\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchoolService
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schools,
        private readonly ActivityLogService $activityLog
    ) {}

    /** @return LengthAwarePaginator<int, School> */
    public function listSchools(SchoolFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->schools->paginate($filters, $perPage);
    }

    /**
     * @return array{recordsTotal:int,recordsFiltered:int,rows:\Illuminate\Support\Collection<int,School>}
     */
    public function listForDataTables(SchoolFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->schools->listForDataTables($filters, $params);
    }

    public function createSchool(CreateSchoolDTO $dto): School
    {
        return $this->wrapWrite(function () use ($dto) {
            $school = $this->schools->create($dto);
            $this->activityLog->logCreated($school);

            return $school;
        });
    }

    public function updateSchool(School $school, UpdateSchoolDTO $dto): School
    {
        return $this->wrapWrite(function () use ($school, $dto) {
            $originalAttributes = $school->getOriginal();
            $updatedSchool = $this->schools->update($school, $dto);

            $changes = [];
            foreach ($updatedSchool->getChanges() as $key => $newValue) {
                if (isset($originalAttributes[$key])) {
                    $changes[$key] = [
                        'old' => $originalAttributes[$key],
                        'new' => $newValue,
                    ];
                }
            }

            if (! empty($changes)) {
                $this->activityLog->logUpdated($updatedSchool, $changes);
            }

            return $updatedSchool;
        });
    }

    public function changeStatus(School $school, ChangeSchoolStatusDTO $dto): School
    {
        return $this->wrapWrite(function () use ($school, $dto) {
            $oldStatus = $school->status->value;
            $updatedSchool = $this->schools->changeStatus($school, $dto);

            $this->activityLog->logStatusChanged(
                $updatedSchool,
                $oldStatus,
                $dto->status->value,
                $dto->reason
            );

            return $updatedSchool;
        });
    }

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
