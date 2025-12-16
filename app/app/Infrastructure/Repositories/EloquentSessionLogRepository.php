<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Enums\SessionLogStatus;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentSessionLogRepository implements SessionLogRepositoryInterface
{
    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog
    {
        return SessionLog::query()
            ->where('id', $sessionLogId)
            ->where('therapist_id', $therapist->id)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule'])
            ->first();
    }

    public function getSessionLogsForTherapist(User $therapist, array $filters = []): Collection
    {
        $query = SessionLog::query()
            ->where('therapist_id', $therapist->id)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['ssa_id'])) {
            $query->where('ssa_id', $filters['ssa_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('session_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('session_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    public function paginateForTherapist(User $therapist, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SessionLog::query()
            ->where('therapist_id', $therapist->id)
            ->with(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['ssa_id'])) {
            $query->where('ssa_id', $filters['ssa_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('session_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('session_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): SessionLog
    {
        return SessionLog::create($data);
    }

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
        $sessionLog->update([
            'status' => SessionLogStatus::SUBMITTED,
            'submitted_at' => now(),
            'submitted_by_id' => $submittedBy->id,
        ]);
        $sessionLog->refresh();

        return $sessionLog;
    }

    public function finalize(SessionLog $sessionLog, User $finalizedBy): SessionLog
    {
        $sessionLog->update([
            'status' => SessionLogStatus::FINALIZED,
            'finalized_at' => now(),
            'finalized_by_id' => $finalizedBy->id,
        ]);
        $sessionLog->refresh();

        return $sessionLog;
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

    public function getActiveSSAsForStudent(int $studentId): Collection
    {
        return ServiceSupportAgreement::query()
            ->where('student_id', $studentId)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['primaryService', 'assignedTherapist'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getSessionLogsForSchedule(int $scheduleId): Collection
    {
        return SessionLog::query()
            ->where('schedule_id', $scheduleId)
            ->with(['student', 'ssa', 'service'])
            ->get();
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SessionLog::query()
            ->with(['student', 'ssa', 'service', 'school', 'therapist'])
            ->latest('session_date');

        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['therapist_id'])) {
            $query->where('therapist_id', $filters['therapist_id']);
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (! empty($filters['ssa_id'])) {
            $query->where('ssa_id', $filters['ssa_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('session_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('session_date', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }
}
