<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\DataTablesParamsDTO;
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

        return $query->orderBy('name')->paginate($filters->perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, User>}
     */
    public function listForDataTables(StudentFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = User::query()
            ->where('users.role', 'student')
            ->leftJoin('student_profiles', 'users.id', '=', 'student_profiles.user_id')
            ->leftJoin('schools', 'student_profiles.school_id', '=', 'schools.id')
            ->select('users.*');

        if ($filters->search) {
            $baseQuery->whereHas('studentProfile', function ($q) use ($filters) {
                $q->search($filters->search);
            });
        }
        if ($filters->status) {
            $baseQuery->where('users.status', $filters->status);
        }
        if ($filters->schoolId) {
            $baseQuery->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('school_id', $filters->schoolId);
            });
        }

        $queryForTotal = (clone $baseQuery)->distinct();
        $recordsTotal = $queryForTotal->count('users.id');

        if ($params->searchValue) {
            $baseQuery->whereHas('studentProfile', function ($q) use ($params) {
                $q->search($params->searchValue);
            });
        }
        $recordsFiltered = (clone $baseQuery)->distinct()->count('users.id');

        $orderColumn = $params->orderColumn ?? 'users.name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->distinct()
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, User>}
     */
    public function listForDataTablesByTherapist(int $therapistId, array $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = User::query()
            ->where('users.role', 'student')
            ->whereHas('studentProfile.ssas', function ($q) use ($therapistId) {
                $q->where('assigned_therapist_id', $therapistId);
            })
            ->leftJoin('student_profiles', 'users.id', '=', 'student_profiles.user_id')
            ->leftJoin('schools', 'student_profiles.school_id', '=', 'schools.id')
            ->select('users.*');

        if (! empty($filters['search'])) {
            $baseQuery->whereHas('studentProfile', function ($q) use ($filters) {
                $q->search($filters['search']);
            });
        }
        if (! empty($filters['status'])) {
            $baseQuery->where('users.status', $filters['status']);
        }

        $recordsTotal = (clone $baseQuery)->distinct()->count('users.id');

        if ($params->searchValue) {
            $baseQuery->whereHas('studentProfile', function ($q) use ($params) {
                $q->search($params->searchValue);
            });
        }
        $recordsFiltered = (clone $baseQuery)->distinct()->count('users.id');

        $orderColumn = $params->orderColumn ?? 'users.name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->distinct()
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
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
        $query = User::query()
            ->where('role', 'student')
            ->whereHas('studentProfile');

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

    /**
     * @return Collection<int, User>
     */
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

        return $query->orderBy('name')->get();
    }

    public function listByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'student')
            ->whereHas('studentProfile.ssas', function ($q) use ($therapistId) {
                $q->where('assigned_therapist_id', $therapistId);
            })
            ->with(['studentProfile.school']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $students = $query->distinct()->orderBy('name')->paginate($perPage);

        // Load SSAs for each student
        $students->load([
            'studentProfile.ssas' => function ($q) use ($therapistId) {
                $q->where('assigned_therapist_id', $therapistId);
            },
        ]);

        return $students;
    }

    public function countStudentsBySchool(int $schoolId): int
    {
        return User::query()
            ->where('role', 'student')
            ->whereHas('studentProfile', fn ($q) => $q->where('school_id', $schoolId))
            ->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsBySchool(int $schoolId): Collection
    {
        return User::query()
            ->where('role', 'student')
            ->where('status', UserStatus::ACTIVE)
            ->whereHas('studentProfile', fn ($q) => $q->where('school_id', $schoolId))
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();
    }

    public function countStudentsByTherapist(int $therapistId): int
    {
        return User::query()
            ->where('role', 'student')
            ->whereHas('therapists', fn ($q) => $q->where('therapist_id', $therapistId))
            ->count();
    }

    public function listStudentsByTherapist(int $therapistId, ?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'student')
            ->whereHas('therapists', fn ($q) => $q->where('therapist_id', $therapistId))
            ->with('studentProfile.school');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return Collection<int, User>
     */
    public function listActiveStudentsByTherapist(int $therapistId): Collection
    {
        return User::query()
            ->where('role', 'student')
            ->where('status', UserStatus::ACTIVE)
            ->whereHas('therapists', fn ($q) => $q->where('therapist_id', $therapistId))
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();
    }

    public function getSchoolIdByUserId(int $userId): ?int
    {
        return StudentProfile::query()
            ->where('user_id', $userId)
            ->value('school_id');
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('role', 'student')
            ->where('email', $email)
            ->first();
    }

    public function findByIdNumber(string $idNumber, int $schoolId): ?StudentProfile
    {
        return StudentProfile::query()
            ->where('id_number', $idNumber)
            ->where('school_id', $schoolId)
            ->first();
    }
}
