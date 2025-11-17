<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateAdminProfileDTO;
use App\DTOs\CreateParentProfileDTO;
use App\DTOs\CreateTherapistProfileDTO;
use App\DTOs\CreateUserDTO;
use App\Enums\Role;
use App\Models\AdminProfile;
use App\Models\ParentProfile;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(CreateUserDTO $dto, string $role): User
    {
        $user = new User;
        $user->name = $dto->name;
        $user->email = $dto->email;
        $user->password = Hash::make($dto->password);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * Count students by status
     * Note: Used by dashboard metrics - preserved for SSA workflow
     */
    public function countStudentsByStatus(string $status): int
    {
        return User::query()->where('role', Role::STUDENT->value)->where('status', $status)->count();
    }

    /**
     * Count new students created this month
     * Note: Used by dashboard metrics - preserved for SSA workflow
     */
    public function countNewStudentsThisMonth(): int
    {
        return User::query()
            ->where('role', Role::STUDENT->value)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function listByRole(string $role): Collection
    {
        return User::query()
            ->where('role', $role)
            ->orderBy('name')
            ->get();
    }

    public function createTherapistProfile(CreateTherapistProfileDTO $dto): TherapistProfile
    {
        return TherapistProfile::create($dto->toArray());
    }

    // Student profile creation removed - will be handled by SSA module
    // TODO: Implement student creation DTOs and methods for SSA workflow

    public function createParentProfile(CreateParentProfileDTO $dto): ParentProfile
    {
        return ParentProfile::create($dto->toArray());
    }

    public function createAdminProfile(CreateAdminProfileDTO $dto): AdminProfile
    {
        return AdminProfile::create($dto->toArray());
    }
}
