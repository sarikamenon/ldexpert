<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Repositories;

use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SessionLogRepositoryInterface
{
    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog;

    public function getSessionLogsForTherapist(User $therapist, array $filters = []): Collection;

    public function paginateForTherapist(User $therapist, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): SessionLog;

    public function update(SessionLog $sessionLog, array $data): SessionLog;

    public function delete(SessionLog $sessionLog): void;

    public function submit(SessionLog $sessionLog, User $submittedBy): SessionLog;

    public function finalize(SessionLog $sessionLog, User $finalizedBy): SessionLog;

    public function cancel(SessionLog $sessionLog, string $reason): SessionLog;

    public function validateTherapistAccessToSSA(User $therapist, int $ssaId): bool;

    public function validateTherapistAccessToStudent(User $therapist, int $studentId): bool;

    public function getActiveSSAsForStudent(int $studentId): Collection;

    public function getSessionLogsForSchedule(int $scheduleId): Collection;

    public function getSessionLogsByScheduleIds(array $scheduleIds): Collection;

    public function paginateForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
