<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateAdminProfileDTO;
use App\DTOs\CreateParentProfileDTO;
use App\DTOs\CreateStudentProfileDTO;
use App\DTOs\CreateTherapistProfileDTO;
use App\DTOs\CreateUserDTO;
use App\Enums\Role;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Models\User;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EloquentUserRepository $repository
    ) {}

    public function createWithRole(CreateUserDTO $dto, string $role): User
    {
        return $this->users->create($dto, $role);
    }

    public function createWithProfile(CreateUserDTO $dto, string $role, array $profileData = []): User
    {
        $user = $this->users->create($dto, $role);

        $profileData['user_id'] = $user->id;

        match ($role) {
            Role::THERAPIST->value => $this->repository->createTherapistProfile(
                CreateTherapistProfileDTO::fromArray($profileData)
            ),
            Role::STUDENT->value => $this->repository->createStudentProfile(
                CreateStudentProfileDTO::fromArray($profileData)
            ),
            Role::PARENT->value => $this->repository->createParentProfile(
                CreateParentProfileDTO::fromArray($profileData)
            ),
            Role::ADMIN->value => $this->repository->createAdminProfile(
                CreateAdminProfileDTO::fromArray($profileData)
            ),
            default => null,
        };

        return $user->fresh();
    }
}
