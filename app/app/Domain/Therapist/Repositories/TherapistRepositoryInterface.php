<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\ChangeTherapistStatusDTO;
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

    public function list(TherapistFilterDTO $filters): Collection;

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User;

    public function getMetrics(?string $status = null): array;

    public function export(TherapistFilterDTO $filters): Collection;

    public function listActiveProfilesForSelect(): Collection;

    public function countTherapistsBySchool(int $schoolId): int;

    public function listActiveTherapistsBySchool(int $schoolId): Collection;

    public function listActiveTherapists(): Collection;

    public function listTherapistsByStudent(int $studentId): Collection;

    public function paginateTherapistsByStudent(int $studentId, ?string $search = null, ?string $status = null, ?string $position = null, int $perPage = 15): LengthAwarePaginator;

    public function findProfileByUserId(int $userId): ?TherapistProfile;
}
