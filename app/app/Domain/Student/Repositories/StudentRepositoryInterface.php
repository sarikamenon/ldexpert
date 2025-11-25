<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\ChangeStudentStatusDTO;
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

    public function list(StudentFilterDTO $filters): LengthAwarePaginator;

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User;

    public function getMetrics(?string $status = null): array;

    public function export(StudentFilterDTO $filters): Collection;
}
