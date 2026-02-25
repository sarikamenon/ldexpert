<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SessionLogRepositoryInterface
{
    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog;

    /** @return Collection<int, SessionLog> */
    public function getSessionLogsForTherapist(User $therapist, array $filters = []): Collection;

    /** @return LengthAwarePaginator<SessionLog> */
    public function paginateForTherapist(User $therapist, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): SessionLog;

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

    /** @return Collection<int, Collection<int, SessionLog>> */
    public function getSessionLogsByScheduleIds(array $scheduleIds, ?User $therapist = null): Collection;

    /** @return LengthAwarePaginator<SessionLog> */
    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTablesForTherapist(User $therapist, array $filters, DataTablesParamsDTO $params): array;
}
