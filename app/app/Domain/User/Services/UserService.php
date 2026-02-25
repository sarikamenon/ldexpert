<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateAdminProfileDTO;
use App\DTOs\CreateParentProfileDTO;
use App\DTOs\CreateTherapistProfileDTO;
use App\DTOs\CreateUserDTO;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function createWithRole(CreateUserDTO $dto, string $role): User
    {
        return $this->repository->create($dto, $role);
    }

    public function createWithProfile(CreateUserDTO $dto, string $role, array $profileData = []): User
    {
        $user = $this->repository->create($dto, $role);

        $profileData['user_id'] = $user->id;

        match ($role) {
            Role::THERAPIST->value => $this->repository->createTherapistProfile(
                CreateTherapistProfileDTO::fromArray($profileData)
            ),
            // Student profile creation removed - will be handled by SSA workflow
            // Role::STUDENT->value => $this->repository->createStudentProfile(...),
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

    /** @return Collection<int, \App\Models\User> */
    public function listByRole(Role $role): Collection
    {
        return $this->repository->listByRole($role->value);
    }

    public function updateProfile(User $user, array $data): User
    {
        return $this->repository->updateProfile($user, $data);
    }

    /** @return Collection<int, \App\Models\User> */
    public function listAdmins(): Collection
    {
        return $this->repository->listAdmins();
    }

    /** @return Collection<int, \App\Models\User> */
    public function listActiveStudentsForSelect(): Collection
    {
        return $this->repository->listActiveStudentsForSelect();
    }

    /** @return Collection<int, \App\Models\User> */
    public function listActiveTherapistsForSelect(): Collection
    {
        return $this->repository->listActiveTherapistsForSelect();
    }
}
