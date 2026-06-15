<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\SessionLogCommentType;
use App\Enums\SessionLogStatus;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SessionLogComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KNOWN GAP — TZ-aware date filters not yet implemented.
 * After the session_log UTC migration, `session_date` is stored in UTC. The
 * `whereDate('session_date', ...)` filters using `date_from` / `date_to` in
 * this file (and `betweenSessionDates` on `SessionLogScope`) compare against
 * the UTC date, not the therapist's local date — so a session at 11pm PT
 * April 30 (stored as `2026-05-01 UTC`) can land in the wrong day bucket
 * when a therapist filters their "April" list. Fix later by converting
 * filter inputs to UTC datetime ranges via `UserTimezoneService::userDayUtcRange()`
 * and using `whereBetween` on the start_time UTC column. See
 * `_local_docs/session-logs-utc-migration-plan.md` ("Known gaps") for context.
 */
final class EloquentSessionLogRepository implements SessionLogRepositoryInterface
{
    /**
     * Approved session log THO minutes grouped by outcome for a student.
     *
     * @return array<string, int>
     */
    public function getOutcomeMinutesForStudent(int $studentId): array
    {
        return SessionLog::query()
            ->forStudentId($studentId)
            ->withStatuses([SessionLogStatus::APPROVED])
            ->withTrackedThoMinutes()
            ->get(['outcome', 'tho_minutes'])
            ->reject(static fn (SessionLog $log): bool => $log->outcome === null)
            ->groupBy(static fn (SessionLog $log): string => $log->outcome->value) // @phpstan-ignore property.nonObject
            ->map(static fn ($group): int => (int) $group->sum('tho_minutes'))
            ->all();
    }

    /**
     * @return array{minutes: int, sessions: int}
     */
    public function getSubmittedSummaryForWeek(User $therapist, Carbon $startOfWeek, Carbon $endOfWeek): array
    {
        /** @var Collection<int, SessionLog> $sessionLogs */
        $sessionLogs = SessionLog::query()
            ->forTherapist($therapist)
            ->betweenSubmittedDates($startOfWeek->toDateTimeString(), $endOfWeek->toDateTimeString())
            ->withStatuses([SessionLogStatus::SUBMITTED, SessionLogStatus::APPROVED])
            ->get();

        return [
            'minutes' => (int) $sessionLogs->sum(
                static fn (SessionLog $sessionLog): int => (int) $sessionLog->duration_minutes
            ),
            'sessions' => $sessionLogs->count(),
        ];
    }

    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog
    {
        return SessionLog::query()
            ->where('id', $sessionLogId)
            ->forTherapist($therapist)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SessionLog>
     */
    public function getSessionLogsForTherapist(User $therapist, array $filters = []): Collection
    {
        $query = SessionLog::query()
            ->forTherapist($therapist)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule']);

        if (isset($filters['status'])) {
            $query->withStatuses([$filters['status']]);
        }

        if (isset($filters['student_id'])) {
            $query->forStudentId((int) $filters['student_id']);
        }

        if (isset($filters['ssa_id'])) {
            $query->forSsaId((int) $filters['ssa_id']);
        }

        if (isset($filters['date_from'])) {
            $query->sessionDateFrom((string) $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->sessionDateTo((string) $filters['date_to']);
        }

        return $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SessionLog>
     */
    public function paginateForTherapist(User $therapist, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SessionLog::query()
            ->forTherapist($therapist)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule']);

        if (isset($filters['status'])) {
            $query->withStatuses([$filters['status']]);
        }

        if (isset($filters['student_id'])) {
            $query->forStudentId((int) $filters['student_id']);
        }

        if (isset($filters['ssa_id'])) {
            $query->forSsaId((int) $filters['ssa_id']);
        }

        if (isset($filters['date_from'])) {
            $query->sessionDateFrom((string) $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->sessionDateTo((string) $filters['date_to']);
        }

        return $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SessionLog
    {
        return SessionLog::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SessionLog $sessionLog, array $data): SessionLog
    {
        $sessionLog->update($data);
        $sessionLog->refresh();

        return $sessionLog;
    }

    public function delete(SessionLog $sessionLog): void
    {
        $sessionLog->delete();
    }

    public function submit(SessionLog $sessionLog, User $submittedBy): SessionLog
    {
        $data = [
            'status' => SessionLogStatus::SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_id' => $submittedBy->id,
        ];
        // Clear sent-back fields when resubmitting from SENT_BACK
        if ($sessionLog->status === SessionLogStatus::SENT_BACK) {
            $data['sent_back_at'] = null;
            $data['sent_back_by_id'] = null;
        }
        $sessionLog->update($data);
        $sessionLog->refresh();

        return $sessionLog;
    }

    public function approve(SessionLog $sessionLog, User $approvedBy): SessionLog
    {
        return DB::transaction(function () use ($sessionLog, $approvedBy): SessionLog {
            // Load service relationship if not already loaded
            if (! $sessionLog->relationLoaded('service')) {
                $sessionLog->load('service');
            }

            // Load SSA if not already loaded
            if (! $sessionLog->relationLoaded('ssa')) {
                $sessionLog->load('ssa');
            }

            // Determine THO minutes based on service and outcome
            $thoMinutes = 0;
            if ($sessionLog->service && $sessionLog->outcome) {
                $service = $sessionLog->service;
                $outcome = $sessionLog->outcome;

                // Check if service allows THO inclusion and outcome should include THO
                if ($service->include_in_tho && $outcome->shouldIncludeInTho()) {
                    $thoMinutes = $sessionLog->duration_minutes ?? 0;
                }
            }

            // Update session log with status and THO minutes
            $sessionLog->update([
                'status' => SessionLogStatus::APPROVED,
                'approved_at' => now(),
                'approved_by_id' => $approvedBy->id,
                'tho_minutes' => $thoMinutes,
            ]);
            $sessionLog->refresh();

            // Update SSA served_minutes if THO minutes > 0
            if ($thoMinutes > 0 && $sessionLog->ssa_id) {
                $ssa = ServiceSupportAgreement::find($sessionLog->ssa_id);
                if ($ssa) {
                    $ssa->increment('served_minutes', $thoMinutes);
                }
            }

            return $sessionLog;
        });
    }

    public function cancel(SessionLog $sessionLog, string $reason): SessionLog
    {
        $sessionLog->update([
            'status' => SessionLogStatus::CANCELLED,
            'cancellation_reason' => $reason,
        ]);
        $sessionLog->refresh();

        return $sessionLog;
    }

    public function sendBack(SessionLog $sessionLog, User $sentBackBy, string $comment): SessionLog
    {
        return DB::transaction(function () use ($sessionLog, $sentBackBy, $comment): SessionLog {
            $sessionLog->update([
                'status' => SessionLogStatus::SENT_BACK,
                'sent_back_at' => now(),
                'sent_back_by_id' => $sentBackBy->id,
            ]);
            SessionLogComment::create([
                'session_log_id' => $sessionLog->id,
                'author_id' => $sentBackBy->id,
                'comment' => $comment,
                'type' => SessionLogCommentType::SENT_BACK,
            ]);
            $sessionLog->refresh();

            return $sessionLog;
        });
    }

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool
    {
        return ServiceSupportAgreement::query()
            ->where('id', $ssaId)
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->exists();
    }

    public function validateTherapistAccessToStudent(User $therapist, int $studentId): bool
    {
        return ServiceSupportAgreement::query()
            ->where('student_id', $studentId)
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->exists();
    }

    /** @return Collection<int, ServiceSupportAgreement> */
    public function getActiveSSAsForStudent(int $studentId): Collection
    {
        return ServiceSupportAgreement::query()
            ->where('student_id', $studentId)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['primaryService', 'assignedTherapist'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /** @return Collection<int, SessionLog> */
    public function getSessionLogsForSchedule(int $scheduleId): Collection
    {
        return SessionLog::query()
            ->forScheduleId($scheduleId)
            ->with(['student', 'ssa', 'service'])
            ->get();
    }

    /**
     * @param  array<int, int>  $scheduleIds
     * @return Collection<int|string, Collection<int, SessionLog>>
     */
    public function getSessionLogsByScheduleIds(array $scheduleIds, ?User $therapist = null): Collection
    {
        $query = SessionLog::query()
            ->forScheduleIds($scheduleIds);

        if ($therapist !== null) {
            $query->forTherapist($therapist);
        }

        /** @var Collection<int|string, Collection<int, SessionLog>> */
        return $query->get()->groupBy('schedule_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SessionLog>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SessionLog::query()
            ->with(['student', 'ssa', 'service', 'school', 'therapist'])
            ->latest('session_date');

        if (! empty($filters['school_id'])) {
            $query->forSchoolId((int) $filters['school_id']);
        }

        if (! empty($filters['student_id'])) {
            $query->forStudentId((int) $filters['student_id']);
        }

        if (! empty($filters['therapist_id'])) {
            $query->forTherapistId((int) $filters['therapist_id']);
        }

        if (! empty($filters['service_id'])) {
            $query->forServiceId((int) $filters['service_id']);
        }

        if (! empty($filters['ssa_id'])) {
            $query->forSsaId((int) $filters['ssa_id']);
        }

        if (! empty($filters['status'])) {
            $query->withStatuses([$filters['status']]);
        }

        if (! empty($filters['date_from'])) {
            $query->sessionDateFrom((string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->sessionDateTo((string) $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array
    {
        $query = SessionLog::query()
            ->with(['student', 'ssa', 'service', 'school', 'therapist'])
            ->latest('session_date');

        if (! empty($filters['school_id'])) {
            $query->forSchoolId((int) $filters['school_id']);
        }
        if (! empty($filters['student_id'])) {
            $query->forStudentId((int) $filters['student_id']);
        }
        if (! empty($filters['therapist_id'])) {
            $query->forTherapistId((int) $filters['therapist_id']);
        }
        if (! empty($filters['service_id'])) {
            $query->forServiceId((int) $filters['service_id']);
        }
        if (! empty($filters['ssa_id'])) {
            $query->forSsaId((int) $filters['ssa_id']);
        }
        if (! empty($filters['status'])) {
            $query->withStatuses([$filters['status']]);
        }
        if (! empty($filters['date_from'])) {
            $query->sessionDateFrom((string) $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->sessionDateTo((string) $filters['date_to']);
        }

        $recordsTotal = (clone $query)->count('session_logs.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $query->where(function (Builder $q) use ($search) {
                $q->where('session_logs.id', 'like', '%'.$search.'%')
                    ->orWhereHas('student', fn (Builder $b) => $b->where('name', 'like', '%'.$search.'%')) // @phpstan-ignore argument.type
                    ->orWhereHas('school', fn (Builder $b) => $b->where('display_name', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%')) // @phpstan-ignore argument.type, argument.type
                    ->orWhereHas('therapist', fn (Builder $b) => $b->where('name', 'like', '%'.$search.'%')) // @phpstan-ignore argument.type
                    ->orWhereHas('service', fn (Builder $b) => $b->where('name', 'like', '%'.$search.'%')); // @phpstan-ignore argument.type
            });
        }
        $recordsFiltered = (clone $query)->count('session_logs.id');

        $orderColumn = $params->orderColumn ?? 'session_logs.session_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, SessionLog> $rows */
        $rows = (clone $query)
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
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTablesForTherapist(User $therapist, array $filters, DataTablesParamsDTO $params): array
    {
        $query = SessionLog::query()
            ->forTherapist($therapist)
            ->with(['student', 'ssa', 'service', 'school'])
            ->latest('session_date');

        if (! empty($filters['student_id'])) {
            $query->forStudentId((int) $filters['student_id']);
        }
        if (! empty($filters['service_id'])) {
            $query->forServiceId((int) $filters['service_id']);
        }
        if (! empty($filters['ssa_id'])) {
            $query->forSsaId((int) $filters['ssa_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->sessionDateFrom((string) $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->sessionDateTo((string) $filters['date_to']);
        }

        $recordsTotal = (clone $query)->count('session_logs.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $query->where(function (Builder $q) use ($search) {
                $q->where('session_logs.id', 'like', '%'.$search.'%')
                    ->orWhereHas('student', fn (Builder $b) => $b->where('name', 'like', '%'.$search.'%')) // @phpstan-ignore argument.type
                    ->orWhereHas('school', fn (Builder $b) => $b->where('display_name', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%')) // @phpstan-ignore argument.type, argument.type
                    ->orWhereHas('service', fn (Builder $b) => $b->where('name', 'like', '%'.$search.'%')); // @phpstan-ignore argument.type
            });
        }
        $recordsFiltered = (clone $query)->count('session_logs.id');

        $orderColumn = $params->orderColumn ?? 'session_logs.session_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, SessionLog> $rows */
        $rows = (clone $query)
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
     * @return Collection<int, SessionLog>
     */
    public function getOrphanLogsForCalendar(ScheduleFilterDTO $filters): Collection
    {
        $query = SessionLog::query()
            ->withoutSchedule()
            ->with(['therapist', 'student', 'service', 'school']);

        if ($filters->therapistIds !== null && $filters->therapistIds !== []) {
            $query->forTherapistIds($filters->therapistIds);
        } elseif ($filters->therapistId !== null) {
            $query->forTherapistId($filters->therapistId);
        }

        if ($filters->studentIds !== null && $filters->studentIds !== []) {
            $query->forStudentIds($filters->studentIds);
        } elseif ($filters->studentId !== null) {
            $query->forStudentId($filters->studentId);
        }

        if ($filters->schoolId !== null) {
            $query->forSchoolId($filters->schoolId);
        }

        if ($filters->dateFrom !== null && $filters->dateTo !== null) {
            $query->betweenSessionDates($filters->dateFrom, $filters->dateTo);
        }

        /** @var Collection<int, SessionLog> $logs */
        $logs = $query->get();

        return $logs;
    }

    /**
     * @param  array<int, SessionLogStatus>  $statuses
     */
    public function existsForSsaWithStatuses(int $ssaId, array $statuses, ?int $excludeSessionLogId = null): bool
    {
        return SessionLog::query()
            ->forSsaId($ssaId)
            ->withStatuses($statuses)
            ->when($excludeSessionLogId !== null, fn ($q) => $q->excludingId((int) $excludeSessionLogId))
            ->exists();
    }

    public function mostRecentSubmittedOrApprovedForSsa(int $ssaId, ?int $excludeSessionLogId = null): ?SessionLog
    {
        return SessionLog::query()
            ->forSsaId($ssaId)
            ->withStatuses([SessionLogStatus::SUBMITTED, SessionLogStatus::APPROVED])
            ->when($excludeSessionLogId !== null, fn ($q) => $q->excludingId((int) $excludeSessionLogId))
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->first();
    }
}
