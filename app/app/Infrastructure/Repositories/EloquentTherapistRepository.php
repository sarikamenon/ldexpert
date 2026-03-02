<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistFilterDTO;
use App\Enums\UserStatus;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentTherapistRepository implements TherapistRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $profileData
     */
    public function create(array $userData, array $profileData): TherapistProfile
    {
        return DB::transaction(function () use ($userData, $profileData) {
            $user = User::create($userData);

            return TherapistProfile::create(array_merge($profileData, ['user_id' => $user->id]));
        });
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $profileData
     */
    public function update(User $user, array $userData, array $profileData): TherapistProfile
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            $user->update($userData);

            // Create profile if it doesn't exist, otherwise update it
            if ($user->therapistProfile) {
                $user->therapistProfile->update($profileData);

                /** @var TherapistProfile $freshProfile */
                $freshProfile = $user->therapistProfile->fresh();

                return $freshProfile;
            } else {
                return $user->therapistProfile()->create($profileData);
            }
        });
    }

    public function find(int $id): ?TherapistProfile
    {
        return TherapistProfile::with(['user', 'manager', 'position'])->find($id);
    }

    /** @return Collection<int, User> */
    public function list(TherapistFilterDTO $filters): Collection
    {
        $query = User::query()
            ->select('users.*')
            ->where('role', 'therapist')
            ->with(['therapistProfile.manager', 'therapistProfile.position'])
            ->distinct();

        if ($filters->search) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->search($filters->search); // @phpstan-ignore method.notFound
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->positionId) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->where('position_id', $filters->positionId); // @phpstan-ignore argument.type
            });
        }

        if ($filters->schoolId) {
            $this->applySchoolFilter($query, $filters->schoolId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, User>}
     */
    public function listForDataTables(TherapistFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = User::query()
            ->where('users.role', 'therapist')
            ->leftJoin('therapist_profiles', 'users.id', '=', 'therapist_profiles.user_id')
            ->leftJoin('positions', 'therapist_profiles.position_id', '=', 'positions.id')
            ->leftJoin('users as managers', 'therapist_profiles.manager_id', '=', 'managers.id')
            ->select('users.*');

        if ($filters->search) {
            $baseQuery->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->search($filters->search); // @phpstan-ignore method.notFound
            });
        }
        if ($filters->status) {
            $baseQuery->where('users.status', $filters->status);
        }
        if ($filters->positionId) {
            $baseQuery->where('therapist_profiles.position_id', $filters->positionId);
        }
        if ($filters->schoolId) {
            $this->applySchoolFilter($baseQuery, $filters->schoolId);
        }
        if ($filters->studentId) {
            $baseQuery->whereHas('assignedSSAs', function ($q) use ($filters) {
                $q->where('student_id', $filters->studentId); // @phpstan-ignore argument.type
            });
        }

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('users.id');

        if ($params->searchValue) {
            $baseQuery->whereHas('therapistProfile', function ($q) use ($params) {
                $q->search($params->searchValue); // @phpstan-ignore method.notFound
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('users.id');

        $orderColumn = $params->orderColumn ?? 'users.name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function changeStatus(User $user, ChangeTherapistStatusDTO $dto): User
    {
        $user->update([
            'status' => $dto->status,
        ]);

        /** @var User $freshUser */
        $freshUser = $user->fresh();

        return $freshUser;
    }

    /** @return array<string, int> */
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

    /** @return Collection<int, User> */
    public function export(TherapistFilterDTO $filters): Collection
    {
        $query = User::query()
            ->select('users.*')
            ->where('role', 'therapist')
            ->with(['therapistProfile.manager', 'therapistProfile.position'])
            ->distinct();

        if ($filters->search) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->search($filters->search); // @phpstan-ignore method.notFound
            });
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->positionId) {
            $query->whereHas('therapistProfile', function ($q) use ($filters) {
                $q->where('position_id', $filters->positionId); // @phpstan-ignore argument.type
            });
        }

        if ($filters->schoolId) {
            $this->applySchoolFilter($query, $filters->schoolId);
        }

        return $query->orderBy('name')->get();
    }

    /** @return Collection<int, TherapistProfile> */
    public function listActiveProfilesForSelect(): Collection
    {
        return TherapistProfile::query()
            ->active()
            ->select(['id', 'first_name', 'last_name'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function countTherapistsBySchool(int $schoolId): int
    {
        return User::query()
            ->where('role', 'therapist')
            ->where(function (Builder $query) use ($schoolId) {
                $query->whereHas('students.studentProfile', function (Builder $studentQuery) use ($schoolId) {
                    $studentQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
                })->orWhereHas('assignedSSAs.student.studentProfile', function (Builder $ssaQuery) use ($schoolId) {
                    $ssaQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
                });
            })
            ->distinct('users.id')
            ->count('users.id');
    }

    /** @return Collection<int, User> */
    public function listActiveTherapistsBySchool(int $schoolId): Collection
    {
        return User::query()
            ->where('role', 'therapist')
            ->where('status', UserStatus::ACTIVE)
            ->where(function (Builder $query) use ($schoolId) {
                $query->whereHas('students.studentProfile', function (Builder $studentQuery) use ($schoolId) {
                    $studentQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
                })->orWhereHas('assignedSSAs.student.studentProfile', function (Builder $ssaQuery) use ($schoolId) {
                    $ssaQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
                });
            })
            ->select(['id', 'name', 'email'])
            ->distinct('users.id')
            ->orderBy('name')
            ->get();
    }

    /** @param Builder<User> $query */
    private function applySchoolFilter(Builder $query, int $schoolId): void
    {
        $query->where(function ($builder) use ($schoolId) {
            $builder->whereHas('students.studentProfile', function ($studentQuery) use ($schoolId) {
                $studentQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
            })->orWhereHas('assignedSSAs.student.studentProfile', function ($ssaQuery) use ($schoolId) {
                $ssaQuery->where('school_id', $schoolId); // @phpstan-ignore argument.type
            });
        });
    }

    public function findProfileByUserId(int $userId): ?TherapistProfile
    {
        return TherapistProfile::query()
            ->where('user_id', $userId)
            ->first();
    }

    /** @return Collection<int, User> */
    public function listActiveTherapists(): Collection
    {
        return User::query()
            ->where('role', 'therapist')
            ->where('status', UserStatus::ACTIVE)
            ->with(['therapistProfile.position'])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    public function listTherapistsByStudent(int $studentId): Collection
    {
        return User::query()
            ->where('role', 'therapist')
            ->whereHas('assignedSSAs', function (Builder $query) use ($studentId) {
                $query->where('student_id', $studentId); // @phpstan-ignore argument.type
            })
            ->with(['therapistProfile.position'])
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginateTherapistsByStudent(int $studentId, ?string $search = null, ?string $status = null, ?int $positionId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'therapist')
            ->whereHas('assignedSSAs', function (Builder $q) use ($studentId) {
                $q->where('student_id', $studentId); // @phpstan-ignore argument.type
            })
            ->with(['therapistProfile.position']);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('therapistProfile', function (Builder $subQ) use ($search) {
                        $subQ->search($search); // @phpstan-ignore method.notFound
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($positionId) {
            $query->whereHas('therapistProfile', function (Builder $q) use ($positionId) {
                $q->where('position_id', $positionId); // @phpstan-ignore argument.type
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
