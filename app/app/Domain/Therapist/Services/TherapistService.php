<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Mail\WelcomeTherapistMail;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class TherapistService
{
    public function __construct(
        private readonly TherapistRepositoryInterface $repository,
    ) {}

    public function create(CreateTherapistDTO $dto): TherapistProfile
    {
        $userData = $dto->toUserArray();
        $userData['password'] = Hash::make($dto->password);

        $profile = $this->repository->create(
            $userData,
            $dto->toProfileArray(0) // user_id will be set in repository
        );

        // Send welcome email
        Mail::to($dto->personalEmail)->send(
            new WelcomeTherapistMail(
                name: $dto->firstName.' '.$dto->lastName,
                email: $dto->personalEmail,
                plainPassword: $dto->password
            )
        );

        return $profile;
    }

    public function update(User $user, UpdateTherapistDTO $dto): TherapistProfile
    {
        return $this->repository->update(
            $user,
            $dto->toUserArray(),
            $dto->toProfileArray()
        );
    }

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User
    {
        return $this->repository->changeStatus($user, $dto);
    }

    public function list(TherapistFilterDTO $filters): Collection
    {
        return $this->repository->list($filters);
    }

    public function getMetrics(?string $status = null): array
    {
        return $this->repository->getMetrics($status);
    }

    public function export(TherapistFilterDTO $filters): Collection
    {
        return $this->repository->export($filters);
    }

    public function find(int $id): ?TherapistProfile
    {
        return $this->repository->find($id);
    }

    public function listActiveProfilesForSelect(): Collection
    {
        return $this->repository->listActiveProfilesForSelect();
    }

    public function countTherapistsBySchool(int $schoolId): int
    {
        return $this->repository->countTherapistsBySchool($schoolId);
    }

    public function listActiveTherapistsBySchool(int $schoolId): Collection
    {
        return $this->repository->listActiveTherapistsBySchool($schoolId);
    }

    public function listActiveTherapists(): Collection
    {
        return $this->repository->listActiveTherapists();
    }

    public function listTherapistsByStudent(int $studentId): Collection
    {
        return $this->repository->listTherapistsByStudent($studentId);
    }

    public function paginateTherapistsByStudent(int $studentId, ?string $search = null, ?string $status = null, ?int $positionId = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateTherapistsByStudent($studentId, $search, $status, $positionId, $perPage);
    }

    public function findProfileByUserId(int $userId): ?TherapistProfile
    {
        return $this->repository->findProfileByUserId($userId);
    }
}
