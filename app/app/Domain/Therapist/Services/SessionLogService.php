<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\SessionLogStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SessionLogService
{
    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
        private readonly SessionLogRateService $rateService,
    ) {}

    public function getSessionLogs(User $therapist, array $filters = []): Collection
    {
        return $this->repository->getSessionLogsForTherapist($therapist, $filters);
    }

    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog
    {
        return $this->repository->findForTherapist($therapist, $sessionLogId);
    }

    public function getActiveSSAsForStudent(int $studentId): Collection
    {
        return $this->repository->getActiveSSAsForStudent($studentId);
    }

    public function createFromSchedule(User $therapist, Schedule $schedule, CreateSessionLogDTO $dto): SessionLog
    {
        return DB::transaction(function () use ($therapist, $schedule, $dto): SessionLog {
            // Validate therapist has access to schedule
            if ($schedule->therapist_id !== $therapist->id) {
                throw new \InvalidArgumentException('Therapist does not have access to this schedule.');
            }

            // Validate therapist has access to SSA
            if (! $this->repository->validateTherapistAccessToSSA($therapist, $dto->ssaId)) {
                throw new \InvalidArgumentException('Therapist does not have access to the selected SSA.');
            }

            // Get SSA for tho_minutes and school_id
            $ssa = ServiceSupportAgreement::with(['student.studentProfile'])->findOrFail($dto->ssaId);
            $thoMinutes = $ssa->minutes_per_session ?? $dto->thoMinutes;
            $schoolId = $schedule->school_id ?? $ssa->student->studentProfile?->school_id ?? null;

            // Calculate billing amounts
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes
            );

            // Prepare data
            $data = $dto->toArray();
            $data['therapist_id'] = $therapist->id;
            $data['schedule_id'] = $schedule->id;
            $data['school_id'] = $schoolId;
            $data['tho_minutes'] = $thoMinutes;

            // Override with calculated billing if not manually set
            if (! $dto->isRateOverride) {
                $data['therapist_contract_id'] = $billing['therapist']['contract_id'];
                $data['therapist_rate_type'] = $billing['therapist']['rate_type']?->value;
                $data['therapist_rate_amount'] = $billing['therapist']['rate_amount'];
                $data['therapist_billable_amount'] = $billing['therapist']['billable_amount'];

                $data['school_contract_id'] = $billing['school']['contract_id'];
                $data['school_rate_type'] = $billing['school']['rate_type']?->value;
                $data['school_rate_amount'] = $billing['school']['rate_amount'];
                $data['school_invoice_amount'] = $billing['school']['invoice_amount'];
            }

            return $this->repository->create($data);
        });
    }

    public function createStandalone(User $therapist, CreateSessionLogDTO $dto): SessionLog
    {
        return DB::transaction(function () use ($therapist, $dto): SessionLog {
            // Validate therapist has access to student
            if (! $this->repository->validateTherapistAccessToStudent($therapist, $dto->studentId)) {
                throw new \InvalidArgumentException('Therapist does not have access to the selected student.');
            }

            // Validate therapist has access to SSA
            if (! $this->repository->validateTherapistAccessToSSA($therapist, $dto->ssaId)) {
                throw new \InvalidArgumentException('Therapist does not have access to the selected SSA.');
            }

            // Get SSA for school_id and tho_minutes
            $ssa = ServiceSupportAgreement::with(['student.studentProfile'])->findOrFail($dto->ssaId);
            $schoolId = $dto->schoolId ?? $ssa->student->studentProfile?->school_id ?? null;
            $thoMinutes = $ssa->minutes_per_session ?? $dto->thoMinutes;

            // Calculate billing amounts
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes
            );

            // Prepare data
            $data = $dto->toArray();
            $data['therapist_id'] = $therapist->id;
            $data['school_id'] = $schoolId;
            $data['tho_minutes'] = $thoMinutes;

            // Override with calculated billing if not manually set
            if (! $dto->isRateOverride) {
                $data['therapist_contract_id'] = $billing['therapist']['contract_id'];
                $data['therapist_rate_type'] = $billing['therapist']['rate_type']?->value;
                $data['therapist_rate_amount'] = $billing['therapist']['rate_amount'];
                $data['therapist_billable_amount'] = $billing['therapist']['billable_amount'];

                $data['school_contract_id'] = $billing['school']['contract_id'];
                $data['school_rate_type'] = $billing['school']['rate_type']?->value;
                $data['school_rate_amount'] = $billing['school']['rate_amount'];
                $data['school_invoice_amount'] = $billing['school']['invoice_amount'];
            }

            return $this->repository->create($data);
        });
    }

    public function update(User $therapist, SessionLog $sessionLog, UpdateSessionLogDTO $dto): SessionLog
    {
        return DB::transaction(function () use ($therapist, $sessionLog, $dto): SessionLog {
            $isAdmin = $therapist->role?->value === 'admin';

            if (! $isAdmin && $sessionLog->therapist_id !== $therapist->id) {
                throw new \InvalidArgumentException('Therapist does not have access to this session log.');
            }

            if (! $isAdmin && ! $sessionLog->canEdit()) {
                throw new \InvalidArgumentException('Session log cannot be edited in its current status.');
            }

            if ($isAdmin && $sessionLog->isFinalized()) {
                throw new \InvalidArgumentException('Finalized session logs cannot be edited.');
            }

            // If duration changed, recalculate billing
            $data = $dto->toArray();
            if (isset($data['duration_minutes']) || isset($data['start_time']) || isset($data['end_time'])) {
                // Recalculate duration if times changed
                if (isset($data['start_time']) && isset($data['end_time'])) {
                    $start = \Carbon\Carbon::parse($data['start_time']);
                    $end = \Carbon\Carbon::parse($data['end_time']);
                    $data['duration_minutes'] = (int) round($start->diffInMinutes($end) / 5) * 5;
                } elseif (isset($data['duration_minutes'])) {
                    // Use provided duration
                } else {
                    $data['duration_minutes'] = $sessionLog->calculateDurationMinutes();
                }

                // Recalculate billing if not overridden
                if (! ($dto->isRateOverride ?? false)) {
                    $sessionDate = $data['session_date'] ?? $sessionLog->session_date->format('Y-m-d');
                    $serviceId = $data['service_id'] ?? $sessionLog->service_id;
                    $schoolId = $data['school_id'] ?? $sessionLog->school_id;
                    $therapistUserId = $isAdmin ? $sessionLog->therapist_id : $therapist->id;

                    $billing = $this->rateService->calculateDualBilling(
                        $therapistUserId,
                        $schoolId,
                        $serviceId,
                        $sessionDate,
                        $data['duration_minutes']
                    );

                    $data['therapist_contract_id'] = $billing['therapist']['contract_id'];
                    $data['therapist_rate_type'] = $billing['therapist']['rate_type']?->value;
                    $data['therapist_rate_amount'] = $billing['therapist']['rate_amount'];
                    $data['therapist_billable_amount'] = $billing['therapist']['billable_amount'];

                    $data['school_contract_id'] = $billing['school']['contract_id'];
                    $data['school_rate_type'] = $billing['school']['rate_type']?->value;
                    $data['school_rate_amount'] = $billing['school']['rate_amount'];
                    $data['school_invoice_amount'] = $billing['school']['invoice_amount'];
                }
            }

            return $this->repository->update($sessionLog, $data);
        });
    }

    public function submit(User $therapist, SessionLog $sessionLog): SessionLog
    {
        if ($sessionLog->therapist_id !== $therapist->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        if (! $sessionLog->canEdit()) {
            throw new \InvalidArgumentException('Session log cannot be submitted in its current status.');
        }

        return $this->repository->submit($sessionLog, $therapist);
    }

    public function finalize(User $admin, SessionLog $sessionLog): SessionLog
    {
        if (! $sessionLog->isSubmitted()) {
            throw new \InvalidArgumentException('Session log must be submitted before finalization.');
        }

        return $this->repository->finalize($sessionLog, $admin);
    }

    public function cancel(User $therapist, SessionLog $sessionLog, string $reason): SessionLog
    {
        if ($sessionLog->therapist_id !== $therapist->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        if (! $sessionLog->status->canCancel()) {
            throw new \InvalidArgumentException('Session log cannot be cancelled in its current status.');
        }

        return $this->repository->cancel($sessionLog, $reason);
    }
}
