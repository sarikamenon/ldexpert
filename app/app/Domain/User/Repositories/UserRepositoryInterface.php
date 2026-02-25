<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\DTOs\CreateAdminProfileDTO;
use App\DTOs\CreateParentProfileDTO;
use App\DTOs\CreateTherapistProfileDTO;
use App\DTOs\CreateUserDTO;
use App\Models\AdminProfile;
use App\Models\ParentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(CreateUserDTO $dto, string $role): User;

    public function findByEmail(string $email): ?User;

    public function countStudentsByStatus(string $status): int;

    public function countNewStudentsThisMonth(): int;

    /** @return Collection<int, User> */
    public function listByRole(string $role): Collection;

    public function updateProfile(User $user, array $data): User;

    /** @return Collection<int, User> */
    public function listAdmins(): Collection;

    /** @return Collection<int, User> */
    public function listActiveStudentsForSelect(): Collection;

    /** @return Collection<int, User> */
    public function listActiveTherapistsForSelect(): Collection;

    /** @return Collection<int, User> */
    public function findByIds(array $ids): Collection;

    public function findById(int $id): ?User;

    public function countActiveStudentsByIds(array $studentIds): int;

    public function createTherapistProfile(CreateTherapistProfileDTO $dto): TherapistProfile;

    public function createParentProfile(CreateParentProfileDTO $dto): ParentProfile;

    public function createAdminProfile(CreateAdminProfileDTO $dto): AdminProfile;
}
