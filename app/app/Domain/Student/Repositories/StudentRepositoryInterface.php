<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\StudentFilterDTO;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StudentRepositoryInterface
{
    public function create(array $userData, array $profileData): StudentProfile;

    public function update(User $user, array $userData, array $profileData): StudentProfile;

    public function find(int $id): ?StudentProfile;

    /** @return LengthAwarePaginator<int, User> */
    public function list(StudentFilterDTO $filters): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, User>}
     */
    public function listForDataTables(StudentFilterDTO $filters, DataTablesParamsDTO $params): array;

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, User>}
     */
    public function listForDataTablesByTherapist(int $therapistId, array $filters, DataTablesParamsDTO $params): array;

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User;

    public function getMetrics(?string $status = null): array;

    /**
     * @return Collection<int, User>
     */
    public function export(StudentFilterDTO $filters): Collection;

    /** @return LengthAwarePaginator<int, User> */
    public function listByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator;

    public function countStudentsBySchool(int $schoolId): int;

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsBySchool(int $schoolId): Collection;

    public function countStudentsByTherapist(int $therapistId): int;

    /** @return LengthAwarePaginator<int, User> */
    public function listStudentsByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsByTherapist(int $therapistId): Collection;

    public function getSchoolIdByUserId(int $userId): ?int;

    public function findByEmail(string $email): ?User;

    public function findByIdNumber(string $idNumber, int $schoolId): ?StudentProfile;
}
