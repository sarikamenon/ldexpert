<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
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
            ->whereIn('status', [ScheduleStatus::SCHEDULED->value, ScheduleStatus::COMPLETED->value])
            ->count();
    }

    public function getSchoolsForTherapist(User $therapist): Collection
    {
        return School::query()
            ->whereHas('studentProfiles.user.therapists', function ($query) use ($therapist) {
                $query->where('users.id', $therapist->id);
            })
            ->orWhereHas('studentProfiles.ssas', function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id);
            })
            ->distinct()
            ->orderBy('display_name')
            ->orderBy('full_name')
            ->get();
    }

    public function getStudentsForTherapist(User $therapist): Collection
    {
        return StudentProfile::query()
            ->whereHas('user.therapists', function ($query) use ($therapist) {
                $query->where('users.id', $therapist->id);
            })
            ->orWhereHas('ssas', function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id);
            })
            ->with('user')
            ->distinct()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

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
                ? trim($studentProfile->first_name . ' ' . $studentProfile->last_name)
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

                $mappings[$studentId]['services'][] = [
                    'ssa_id' => $ssa->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'is_group_service' => $service->is_group_service,
                    'is_primary' => (bool) $service->pivot?->is_primary,
                ];
            }
        }

        return collect($mappings)->map(function (array $entry) {
            $entry['services'] = collect($entry['services'])
                ->unique(fn($service) => $service['service_id'] . '-' . $service['ssa_id'])
                ->values()
                ->all();

            return $entry;
        });
    }

    public function create(array $data): Schedule
    {
        return Schedule::create($data);
    }

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

    public function getRecurringOccurrences(Schedule $parentSchedule): Collection
    {
        return Schedule::query()
            ->where('parent_schedule_id', $parentSchedule->id)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getRecurringOccurrencesByBatch(string $recurringBatchNumber): Collection
    {
        return Schedule::query()
            ->byRecurringBatch($recurringBatchNumber)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getGroupSchedulesByBatch(string $groupBatchNumber): Collection
    {
        return Schedule::query()
            ->byGroupBatch($groupBatchNumber)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getSchedulesForStudent(User $student, array $filters = []): Collection
    {
        $query = Schedule::query()
            ->forStudent($student)
            ->with(['therapist', 'service', 'ssa', 'school']);

        if (! empty($filters['date'])) {
            $query->whereDate('schedule_date', $filters['date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['billing_status'])) {
            $query->where('billing_status', $filters['billing_status']);
        }

        return $query->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    }

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool
    {
        return ServiceSupportAgreement::query()
            ->whereKey($ssaId)
            ->where('assigned_therapist_id', $therapist->id)
            ->exists();
    }

    public function validateTherapistAccessToStudents(User $therapist, array $studentIds): bool
    {
        if ($studentIds === []) {
            return true;
        }

        $count = StudentProfile::query()
            ->whereIn('user_id', $studentIds)
            ->where(function ($query) use ($therapist) {
                $query->whereHas('user.therapists', function ($q) use ($therapist) {
                    $q->where('users.id', $therapist->id);
                })
                    ->orWhereHas('ssas', function ($q) use ($therapist) {
                        $q->where('assigned_therapist_id', $therapist->id);
                    });
            })
            ->count();

        return $count === count(array_unique($studentIds));
    }

    public function validateStudentsShareService(User $therapist, array $studentIds, int $serviceId): bool
    {
        if ($studentIds === []) {
            return false;
        }

        $studentsWithService = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->whereIn('student_id', $studentIds)
            ->where(function ($query) use ($serviceId) {
                $query->where('primary_service_id', $serviceId)
                    ->orWhereHas('services', function ($relation) use ($serviceId) {
                        $relation->where('services.id', $serviceId);
                    });
            })
            ->distinct('student_id')
            ->pluck('student_id');

        return $studentsWithService->count() === count(array_unique($studentIds));
    }

    public function generateBatchNumber(string $type = 'recurring'): string
    {
        $prefix = $type === 'group' ? 'GRP' : 'REC';

        return sprintf('%s-%s', $prefix, Str::uuid()->toString());
    }

    public function updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule
    {
        $schedule->billing_status = $status;
        $schedule->save();

        return $schedule;
    }

    public function bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int
    {
        if ($scheduleIds === []) {
            return 0;
        }

        return Schedule::query()
            ->whereIn('id', $scheduleIds)
            ->update(['billing_status' => $status->value]);
    }
}
