<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\TherapistFilterDTO;
use App\Enums\UserStatus;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentTherapistRepository implements TherapistRepositoryInterface
{
    public function create(array $userData, array $profileData): TherapistProfile
    {
        return DB::transaction(function () use ($userData, $profileData) {
            $user = User::create($userData);
            return TherapistProfile::create(array_merge($profileData, ['user_id' => $user->id]));
        });
    }

    public function update(User $user, array $userData, array $profileData): TherapistProfile
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            $user->update($userData);

            // Create profile if it doesn't exist, otherwise update it
            if ($user->therapistProfile) {
                $user->therapistProfile->update($profileData);
                return $user->therapistProfile->fresh();
            } else {
                return $user->therapistProfile()->create($profileData);
            }
        });
    }

    public function find(int $id): ?TherapistProfile
    {
        return TherapistProfile::with(['user', 'manager'])->find($id);
    }

    public function list(TherapistFilterDTO $filters): Collection
    {
        $query = User::query()
            ->where('role', 'therapist')
            ->with(['therapistProfile.manager']);

        if ($filters->search) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->search($filters->search);
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->position) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->where('position', $filters->position);
            });
        }

        return $query->latest()->get();
    }

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User
    {
        $user->update([
            'status' => $dto->status,
        ]);

        return $user->fresh();
    }

    public function getMetrics(?string $status = null): array
    {
        $query = User::query()->where('role', 'therapist');

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

    public function export(TherapistFilterDTO $filters): Collection
    {
        $query = User::query()
            ->where('role', 'therapist')
            ->with(['therapistProfile.manager']);

        if ($filters->search) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->search($filters->search);
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->position) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->where('position', $filters->position);
            });
        }

        return $query->latest()->get();
    }
}
