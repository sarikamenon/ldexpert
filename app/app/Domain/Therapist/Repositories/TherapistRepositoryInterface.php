<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistFilterDTO;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TherapistRepositoryInterface
{
    public function create(array $userData, array $profileData): TherapistProfile;

    public function update(User $user, array $userData, array $profileData): TherapistProfile;

    public function find(int $id): ?TherapistProfile;

    /** @return Collection<int, User> */
    public function list(TherapistFilterDTO $filters): Collection;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, User>}
     */
    public function listForDataTables(TherapistFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User;

    public function getMetrics(?string $status = null): array;

    /** @return Collection<int, User> */
    public function export(TherapistFilterDTO $filters): Collection;

    /** @return Collection<int, TherapistProfile> */
    public function listActiveProfilesForSelect(): Collection;

    public function countTherapistsBySchool(int $schoolId): int;

    /** @return Collection<int, User> */
    public function listActiveTherapistsBySchool(int $schoolId): Collection;

    /** @return Collection<int, User> */
    public function listActiveTherapists(): Collection;

    /** @return Collection<int, User> */
    public function listTherapistsByStudent(int $studentId): Collection;

    /** @return LengthAwarePaginator<int, User> */
    public function paginateTherapistsByStudent(int $studentId, ?string $search = null, ?string $status = null, ?int $positionId = null, int $perPage = 15): LengthAwarePaginator;

    public function findProfileByUserId(int $userId): ?TherapistProfile;
}
