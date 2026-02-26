<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Mail\WelcomeStudentMail;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class StudentService
{
    public function __construct(
        private readonly StudentRepositoryInterface $repository,
    ) {}

    public function create(CreateStudentDTO $dto): StudentProfile
    {
        $userData = $dto->toUserArray();
        $userData['password'] = Hash::make($dto->password);

        $profile = $this->repository->create(
            $userData,
            $dto->toProfileArray(0) // user_id will be set in repository
        );

        // Send welcome email to student's user email
        Mail::to($dto->email)->send(
            new WelcomeStudentMail(
                name: $dto->firstName.' '.$dto->lastName,
                email: $dto->email,
                plainPassword: $dto->password
            )
        );

        return $profile;
    }

    public function update(User $user, UpdateStudentDTO $dto): StudentProfile
    {
        return $this->repository->update(
            $user,
            $dto->toUserArray(),
            $dto->toProfileArray()
        );
    }

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User
    {
        return $this->repository->changeStatus($user, $dto);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function list(StudentFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, User>}
     */
    public function listForDataTables(StudentFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, User>}
     */
    public function listForDataTablesByTherapist(int $therapistId, array $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTablesByTherapist($therapistId, $filters, $params);
    }

    /** @return array<string, int> */
    public function getMetrics(?string $status = null): array
    {
        return $this->repository->getMetrics($status);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->listByTherapist($therapistId, $search, $status, $perPage);
    }

    public function countStudentsBySchool(int $schoolId): int
    {
        return $this->repository->countStudentsBySchool($schoolId);
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsBySchool(int $schoolId): Collection
    {
        return $this->repository->listActiveStudentsBySchool($schoolId);
    }

    /**
     * @return Collection<int, User>
     */
    public function export(StudentFilterDTO $filters): Collection
    {
        return $this->repository->export($filters);
    }

    public function find(int $id): ?StudentProfile
    {
        return $this->repository->find($id);
    }

    public function countStudentsByTherapist(int $therapistId): int
    {
        return $this->repository->countStudentsByTherapist($therapistId);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listStudentsByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->listStudentsByTherapist($therapistId, $search, $status, $perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsByTherapist(int $therapistId): Collection
    {
        return $this->repository->listActiveStudentsByTherapist($therapistId);
    }

    public function getSchoolIdByUserId(int $userId): ?int
    {
        return $this->repository->getSchoolIdByUserId($userId);
    }
}
