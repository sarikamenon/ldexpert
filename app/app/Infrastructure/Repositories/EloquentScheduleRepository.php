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
use Carbon\Carbon;
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
            ->whereHas('studentProfiles.ssas', function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id)
                    ->where('status', SSAStatus::ACTIVE);
            })
            ->orWhereHas('studentProfiles.user', function ($query) use ($therapist) {
                $query->whereHas('therapists', function ($q) use ($therapist) {
                    $q->where('therapist_id', $therapist->id);
                });
            })
            ->orderBy('display_name')
            ->get();
    }

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
            ->where('id', $ssaId)
            ->where('assigned_therapist_id', $therapist->id)
            ->exists();
    }

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
        return $prefix . '-' . Str::random(10) . '-' . time();
    }

    public function updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule
    {
        $schedule->billing_status = $status;
        $schedule->save();

        return $schedule;
    }

    public function bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int
    {
        return Schedule::query()
            ->whereIn('id', $scheduleIds)
            ->update(['billing_status' => $status]);
    }

    public function hasOverlap(User $user, string $date, string $startTime, string $endTime, ?int $excludeScheduleId = null): bool
    {
        $query = Schedule::query()
            ->where('schedule_date', $date)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            })
            ->where('status', '!=', ScheduleStatus::CANCELLED->value);

        // Check for therapist or student overlap
        $query->where(function ($q) use ($user) {
            $q->where('therapist_id', $user->id)
                ->orWhere('student_id', $user->id);
        });

        if ($excludeScheduleId) {
            $query->where('id', '!=', $excludeScheduleId);
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

    public function getSchedulesForReminder(Carbon $start, Carbon $end): Collection
    {
        return $this->getSchedulesInWindow($start, $end);
    }
}
