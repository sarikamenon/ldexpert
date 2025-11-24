<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\StudentFilterDTO;
use App\Enums\UserStatus;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function create(array $userData, array $profileData): StudentProfile
    {
        return DB::transaction(function () use ($userData, $profileData) {
            $user = User::create($userData);
            return StudentProfile::create(array_merge($profileData, ['user_id' => $user->id]));
        });
    }

    public function update(User $user, array $userData, array $profileData): StudentProfile
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            $user->update($userData);

            // Create profile if it doesn't exist, otherwise update it
            if ($user->studentProfile) {
                $user->studentProfile->update($profileData);
                return $user->studentProfile->fresh();
            } else {
                return $user->studentProfile()->create($profileData);
            }
        });
    }

    public function find(int $id): ?StudentProfile
    {
        return StudentProfile::with(['user', 'school', 'parent'])->find($id);
    }

    public function list(StudentFilterDTO $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'student')
            ->with(['studentProfile.school', 'studentProfile.parent']);

        if ($filters->search) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->search($filters->search);
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->schoolId) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('school_id', $filters->schoolId);
            });
        }

        return $query->latest()->paginate($filters->perPage);
    }

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User
    {
        $user->update([
            'status' => $dto->status,
        ]);

        return $user->fresh();
    }

    public function getMetrics(?string $status = null): array
    {
        $query = User::query()->where('role', 'student');

        if ($status) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', UserStatus::ACTIVE->value)->count();
        $inactive = (clone $query)->where('status', UserStatus::INACTIVE->value)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function export(StudentFilterDTO $filters): Collection
    {
        $query = User::query()
            ->where('role', 'student')
            ->with(['studentProfile.school', 'studentProfile.parent']);

        if ($filters->search) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->search($filters->search);
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->schoolId) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('school_id', $filters->schoolId);
            });
        }

        return $query->latest()->get();
    }
}
