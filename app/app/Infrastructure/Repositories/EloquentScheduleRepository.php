<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
    /** @return Collection<int, Schedule> */
    public function getSchedulesForTherapist(User $therapist, ScheduleFilterDTO $filters): Collection
    {
        $query = Schedule::query()
            ->forTherapist($therapist)
            ->with(['student', 'student.studentProfile', 'service', 'ssa', 'school']);

        if ($filters->date) {
            $query->whereDate('schedule_date', $filters->date);
        }

        if ($filters->schoolId) {
            $query->where('school_id', $filters->schoolId);
        }

        if ($filters->studentId) {
            $query->where('student_id', $filters->studentId);
        }

        return $query->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getPendingCount(User $therapist): int
    {
        return Schedule::query()
            ->forTherapist($therapist)
            ->whereDate('schedule_date', '<', now()->toDateString())
            ->where('billing_status', BillingStatus::PENDING->value)
            ->withStatuses([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED])
            ->count();
    }

    /** @return Collection<int, Schedule> */
    public function getPendingSchedules(User $therapist, ?ScheduleFilterDTO $filters = null): Collection
    {
        $query = Schedule::query()
            ->forTherapist($therapist)
            ->whereDate('schedule_date', '<', now()->toDateString())
            ->where('billing_status', BillingStatus::PENDING->value)
            ->withStatuses([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED])
            ->with(['student', 'service', 'ssa', 'school']);

        if ($filters) {
            if ($filters->studentId) {
                $query->where('student_id', $filters->studentId);
            }

            if ($filters->ssaId) {
                $query->where('ssa_id', $filters->ssaId);
            }

            if ($filters->serviceId) {
                $query->where('service_id', $filters->serviceId);
            }

            if ($filters->dateFrom) {
                $query->whereDate('schedule_date', '>=', $filters->dateFrom);
            }

            if ($filters->dateTo) {
                $query->whereDate('schedule_date', '<=', $filters->dateTo);
            }
        }

        return $query->orderBy('schedule_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /** @return Collection<int, School> */
    public function getSchoolsForTherapist(User $therapist): Collection
    {
        return School::query()
            ->whereHas('studentProfiles.ssas', function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id) // @phpstan-ignore argument.type
                    ->where('status', SSAStatus::ACTIVE); // @phpstan-ignore argument.type
            })
            ->orWhereHas('studentProfiles.user', function ($query) use ($therapist) {
                $query->whereHas('therapists', function ($q) use ($therapist) {
                    $q->where('therapist_id', $therapist->id); // @phpstan-ignore argument.type
                });
            })
            ->orderBy('display_name')
            ->get();
    }

    /** @return Collection<int, User> */
    public function getStudentsForTherapist(User $therapist): Collection
    {
        $studentIds = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->pluck('student_id');

        return User::query()
            ->whereIn('id', $studentIds)
            ->with(['studentProfile'])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getStudentServiceMappings(User $therapist): Collection
    {
        $ssas = ServiceSupportAgreement::query()
            ->with([
                'student',
                'student.studentProfile',
                'primaryService',
                'services',
            ])
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->get();

        $mappings = [];

        foreach ($ssas as $ssa) {
            $studentId = $ssa->student_id;
            $studentProfile = $ssa->student?->studentProfile;
            $studentName = $studentProfile
                ? trim($studentProfile->first_name.' '.$studentProfile->last_name)
                : $ssa->student?->name;

            if (! isset($mappings[$studentId])) {
                $mappings[$studentId] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'services' => [],
                ];
            }

            foreach ($ssa->services as $service) {
                if ($service->status !== ServiceStatus::ACTIVE) {
                    continue;
                }

                /** @var \App\Models\Pivots\SSAService|null $pivot */
                $pivot = $service->getRelation('pivot');

                $mappings[$studentId]['services'][] = [
                    'ssa_id' => $ssa->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'is_group_service' => $service->is_group_service,
                    'is_primary' => (bool) $pivot?->is_primary,
                ];
            }
        }

        /** @var Collection<int, array<string, mixed>> */
        return collect($mappings)->map(function (array $entry) {
            $entry['services'] = collect($entry['services'])
                ->unique(fn ($service) => $service['service_id'].'-'.$service['ssa_id'])
                ->values()
                ->all();

            return $entry;
        });
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Schedule
    {
        return Schedule::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Schedule $schedule, array $data): Schedule
    {
        $schedule->fill($data);
        $schedule->save();

        return $schedule;
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function findForTherapist(User $therapist, int $scheduleId): ?Schedule
    {
        return Schedule::query()
            ->forTherapist($therapist)
            ->whereKey($scheduleId)
            ->first();
    }

    /** @return Collection<int, Schedule> */
    public function getRecurringOccurrences(Schedule $parentSchedule): Collection
    {
        return Schedule::query()
            ->where('parent_schedule_id', $parentSchedule->id)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, Schedule> */
    public function getRecurringOccurrencesByBatch(string $recurringBatchNumber): Collection
    {
        return Schedule::query()
            ->byRecurringBatch($recurringBatchNumber)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, Schedule> */
    public function getUnbilledFutureRecurringOccurrencesByBatch(string $recurringBatchNumber, string $fromDate): Collection
    {
        return Schedule::query()
            ->byRecurringBatch($recurringBatchNumber)
            ->scheduleDateFrom($fromDate)
            ->unbilled()
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    /** @return Collection<int, Schedule> */
    public function getGroupSchedulesByBatch(string $groupBatchNumber): Collection
    {
        return Schedule::query()
            ->byGroupBatch($groupBatchNumber)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Schedule>
     */
    public function getSchedulesForStudent(User $student, array $filters = []): Collection
    {
        $dto = new ScheduleFilterDTO(
            date: $filters['date'] ?? null,
            schoolId: isset($filters['school_id']) ? (int) $filters['school_id'] : null,
            studentId: isset($filters['student_id']) ? (int) $filters['student_id'] : null,
            status: $filters['status'] ?? null,
            billingStatus: $filters['billing_status'] ?? null,
            ssaId: isset($filters['ssa_id']) ? (int) $filters['ssa_id'] : null,
            therapistId: isset($filters['therapist_id']) ? (int) $filters['therapist_id'] : null,
        );

        return $this->buildStudentScheduleQuery($student, $dto)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginateForStudent(User $student, ScheduleFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildStudentScheduleQuery($student, $filters)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, Schedule>}
     */
    public function listForDataTablesForStudent(User $student, ScheduleFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->buildStudentScheduleQuery($student, $filters);

        $recordsTotal = (clone $baseQuery)->count();

        if ($params->searchValue) {
            $sv = $params->searchValue;
            $baseQuery->where(function ($q) use ($sv) {
                $q->whereHas('therapist', fn ($q2) => $q2->where('name', 'like', "%{$sv}%")) // @phpstan-ignore argument.type
                    ->orWhereHas('service', fn ($q2) => $q2->where('name', 'like', "%{$sv}%")) // @phpstan-ignore argument.type
                    ->orWhereHas('school', fn ($q2) => $q2->where('display_name', 'like', "%{$sv}%")); // @phpstan-ignore argument.type
            });
        }
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumn = $params->orderColumn ?? 'schedule_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);
        if ($orderColumn !== 'start_time') {
            $baseQuery->orderBy('start_time', $orderDir);
        }

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

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool
    {
        return ServiceSupportAgreement::query()
            ->where('id', $ssaId)
            ->where('assigned_therapist_id', $therapist->id)
            ->exists();
    }

    /** @param array<int, int> $studentIds */
    public function validateTherapistAccessToStudents(User $therapist, array $studentIds): bool
    {
        // Check if therapist has access to all students via active SSAs
        $count = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->whereIn('student_id', $studentIds)
            ->where('status', SSAStatus::ACTIVE)
            ->distinct('student_id')
            ->count('student_id');

        return $count === count(array_unique($studentIds));
    }

    /** @param array<int, int> $studentIds */
    public function validateStudentsShareService(User $therapist, array $studentIds, int $serviceId): bool
    {
        // Check if all students have an active SSA with this service assigned to this therapist
        $count = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->whereIn('student_id', $studentIds)
            ->where('status', SSAStatus::ACTIVE)
            ->whereHas('services', function ($query) use ($serviceId) {
                $query->where('services.id', $serviceId)
                    ->where('services.status', ServiceStatus::ACTIVE);
            })
            ->distinct('student_id')
            ->count('student_id');

        return $count === count(array_unique($studentIds));
    }

    public function generateBatchNumber(string $type = 'recurring'): string
    {
        $prefix = $type === 'recurring' ? 'REC' : 'GRP';

        return $prefix.'-'.Str::random(10).'-'.time();
    }

    public function updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule
    {
        $schedule->billing_status = $status;
        $schedule->save();

        return $schedule;
    }

    /** @param array<int, int> $scheduleIds */
    public function bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int
    {
        return Schedule::query()
            ->whereIn('id', $scheduleIds)
            ->update(['billing_status' => $status]);
    }

    public function hasOverlap(User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions): bool
    {
        $query = Schedule::query()
            ->where('schedule_date', $check->date)
            ->where(function ($q) use ($check) {
                $q->where(function ($query) use ($check) {
                    $query->where('start_time', '<', $check->endTime)
                        ->where('end_time', '>', $check->startTime);
                });
            })
            ->where('status', '!=', ScheduleStatus::CANCELLED->value)
            // Exclude recurring parent records — they are rule templates, not actual sessions.
            // Real sessions are either non-recurring (recurrence_type = none) or child occurrences
            // (parent_schedule_id is not null).
            ->where(function ($q) {
                $q->where('recurrence_type', RecurrenceType::NONE->value)
                    ->orWhereNotNull('parent_schedule_id');
            });

        // Check for therapist or student overlap
        $query->where(function ($q) use ($user) {
            $q->where('therapist_id', $user->id)
                ->orWhere('student_id', $user->id);
        });

        if ($exclusions->scheduleId !== null) {
            $query->where('id', '!=', $exclusions->scheduleId);
        }

        if ($exclusions->batchNumber !== null) {
            $query->where(function ($q) use ($exclusions) {
                $q->whereNull('recurring_batch_number')
                    ->orWhere('recurring_batch_number', '!=', $exclusions->batchNumber);
            });
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedulesInWindow(Carbon $start, Carbon $end): Collection
    {
        $dates = array_unique([
            $start->toDateString(),
            $end->toDateString(),
        ]);

        return Schedule::query()
            ->with(['therapist', 'therapist.therapistProfile', 'student.studentProfile.parent', 'service'])
            ->whereIn('schedule_date', $dates)
            ->where('status', ScheduleStatus::SCHEDULED->value)
            ->get()
            ->filter(function (Schedule $schedule) use ($start, $end) {
                $scheduleDateTime = $schedule->schedule_date->copy()->setTime(
                    $schedule->start_time->hour,
                    $schedule->start_time->minute,
                    0
                );

                return $scheduleDateTime->between($start, $end);
            });
    }

    /** @return Collection<int, Schedule> */
    public function getSchedulesForReminder(Carbon $start, Carbon $end): Collection
    {
        return $this->getSchedulesInWindow($start, $end);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Schedule> */
    private function buildStudentScheduleQuery(User $student, ScheduleFilterDTO $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Schedule::query()
            ->forStudent($student)
            ->with(['therapist', 'service', 'ssa', 'school']);

        if ($filters->date) {
            $query->whereDate('schedule_date', $filters->date);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->billingStatus) {
            $query->where('billing_status', $filters->billingStatus);
        }

        if ($filters->ssaId) {
            $query->where('ssa_id', $filters->ssaId);
        }

        if ($filters->therapistIds !== null) {
            $query->whereIn('therapist_id', $filters->therapistIds);
        } elseif ($filters->therapistId) {
            $query->where('therapist_id', $filters->therapistId);
        }

        if ($filters->schoolId) {
            $query->where('school_id', $filters->schoolId);
        }

        if ($filters->studentId) {
            $query->where('student_id', $filters->studentId);
        }

        return $query;
    }

    public function countLessonsThisWeek(User $therapist, Carbon $startOfWeek, Carbon $endOfWeek): int
    {
        return Schedule::query()
            ->forTherapist($therapist)
            ->betweenScheduleDates($startOfWeek->toDateString(), $endOfWeek->toDateString())
            ->withStatuses([ScheduleStatus::SCHEDULED, ScheduleStatus::COMPLETED])
            ->count();
    }

    /** @return Collection<int, Schedule> */
    public function getSchedulesForCalendar(ScheduleFilterDTO $filters): Collection
    {
        $query = Schedule::query()
            ->with(['therapist', 'student', 'service', 'school']);

        if ($filters->therapistIds !== null) {
            $query->whereIn('therapist_id', $filters->therapistIds);
        } elseif ($filters->therapistId) {
            $query->where('therapist_id', $filters->therapistId);
        }

        if ($filters->studentIds !== null) {
            $query->whereIn('student_id', $filters->studentIds);
        } elseif ($filters->studentId) {
            $query->where('student_id', $filters->studentId);
        }

        if ($filters->schoolId) {
            $query->where('school_id', $filters->schoolId);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->billingStatus) {
            $query->where('billing_status', $filters->billingStatus);
        }

        if ($filters->dateFrom) {
            $query->whereDate('schedule_date', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo) {
            $query->whereDate('schedule_date', '<=', $filters->dateTo);
        }

        return $query->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }
}
