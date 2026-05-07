<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SessionLogRepositoryInterface
{
    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SessionLog>
     */
    public function getSessionLogsForTherapist(User $therapist, array $filters = []): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SessionLog>
     */
    public function paginateForTherapist(User $therapist, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** @param array<string, mixed> $data */
    public function create(array $data): SessionLog;

    /** @param array<string, mixed> $data */
    public function update(SessionLog $sessionLog, array $data): SessionLog;

    public function delete(SessionLog $sessionLog): void;

    public function submit(SessionLog $sessionLog, User $submittedBy): SessionLog;

    public function approve(SessionLog $sessionLog, User $approvedBy): SessionLog;

    public function cancel(SessionLog $sessionLog, string $reason): SessionLog;

    public function sendBack(SessionLog $sessionLog, User $sentBackBy, string $comment): SessionLog;

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool;

    public function validateTherapistAccessToStudent(User $therapist, int $studentId): bool;

    /** @return Collection<int, \App\Models\ServiceSupportAgreement> */
    public function getActiveSSAsForStudent(int $studentId): Collection;

    /** @return Collection<int, SessionLog> */
    public function getSessionLogsForSchedule(int $scheduleId): Collection;

    /**
     * @param  array<int, int>  $scheduleIds
     * @return Collection<int|string, Collection<int, SessionLog>>
     */
    public function getSessionLogsByScheduleIds(array $scheduleIds, ?User $therapist = null): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SessionLog>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTablesForTherapist(User $therapist, array $filters, DataTablesParamsDTO $params): array;

    /**
     * @return array{minutes: int, sessions: int}
     */
    public function getSubmittedSummaryForWeek(User $therapist, Carbon $startOfWeek, Carbon $endOfWeek): array;

    /**
     * Submitted/approved session log minutes grouped by outcome for a student.
     *
     * @return array<string, int> outcome value => total minutes
     */
    public function getOutcomeMinutesForStudent(int $studentId): array;

    /**
     * Session logs without an associated schedule, scoped for the calendar.
     * Used to render orphan logs alongside schedules on the therapist calendar.
     *
     * @return Collection<int, SessionLog>
     */
    public function getOrphanLogsForCalendar(ScheduleFilterDTO $filters): Collection;
}
