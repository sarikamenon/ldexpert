<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\TherapistFilterDTO;
use App\Models\TherapistProfile;
use App\Models\User;
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
}
