<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\BillingStatus;
use App\Enums\Role;
use App\Enums\SessionOutcome;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SessionLogService
{
    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
        private readonly SessionLogRateService $rateService,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
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

    public function getSessionLogsByScheduleIds(array $scheduleIds): Collection
    {
        return $this->repository->getSessionLogsByScheduleIds($scheduleIds);
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
            $ssa = $this->ssaRepository->findWithRelations($dto->ssaId, ['student.studentProfile']);
            if (! $ssa) {
                throw new \InvalidArgumentException('SSA not found.');
            }
            $service = $this->serviceRepository->findOrFail($dto->serviceId);
            $this->assertSessionDateWithinSsa($dto->sessionDate, $ssa->start_date, $ssa->end_date);
            $this->assertDurationWithinServiceBounds($dto->durationMinutes, $service->min_duration_minutes, $service->max_duration_minutes);
            $thoMinutes = $ssa->minutes_per_session ?? $dto->thoMinutes;
            $schoolId = $schedule->school_id ?? $ssa->student->studentProfile?->school_id ?? null;
            $this->assertSchoolIdPresent($schoolId);

            $this->assertScheduleNotBilled($schedule);
            $this->assertScheduleHasNoLogs($schedule);

            // Calculate billing amounts and validate contracts
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes
            );
            $this->assertBillingDataComplete($billing);

            // Prepare data
            $data = $dto->toArray();
            $data['therapist_id'] = $therapist->id;
            $data['schedule_id'] = $schedule->id;
            $data['school_id'] = $schoolId;
            $data['tho_minutes'] = $thoMinutes;

            // Apply billing flags based on outcome
            $this->applyOutcomeBillingFlags($data);

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

            $sessionLog = $this->repository->create($data);

            $schedule->update(['billing_status' => BillingStatus::BILLED]);

            return $sessionLog;
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
            $ssa = $this->ssaRepository->findWithRelations($dto->ssaId, ['student.studentProfile']);
            if (! $ssa) {
                throw new \InvalidArgumentException('SSA not found.');
            }
            $service = $this->serviceRepository->findOrFail($dto->serviceId);
            $this->assertSessionDateWithinSsa($dto->sessionDate, $ssa->start_date, $ssa->end_date);
            $this->assertDurationWithinServiceBounds($dto->durationMinutes, $service->min_duration_minutes, $service->max_duration_minutes);
            $schoolId = $dto->schoolId ?? $ssa->student->studentProfile?->school_id ?? null;
            $this->assertSchoolIdPresent($schoolId);
            $thoMinutes = $ssa->minutes_per_session ?? $dto->thoMinutes;

            // Calculate billing amounts and validate contracts
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes
            );
            $this->assertBillingDataComplete($billing);

            // Prepare data
            $data = $dto->toArray();
            $data['therapist_id'] = $therapist->id;
            $data['school_id'] = $schoolId;
            $data['tho_minutes'] = $thoMinutes;

            // Apply billing flags based on outcome
            $this->applyOutcomeBillingFlags($data);

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

            if ($isAdmin && $sessionLog->isApproved()) {
                throw new \InvalidArgumentException('Approved session logs cannot be edited.');
            }

            $data = $dto->toArray();

            // If outcome is provided, update billing flags accordingly
            if (isset($data['outcome'])) {
                $this->applyOutcomeBillingFlags($data);
            }

            // If duration changed, recalculate billing
            if (isset($data['duration_minutes']) || isset($data['start_time']) || isset($data['end_time'])) {
                // Recalculate duration if times changed
                if (isset($data['start_time']) && isset($data['end_time'])) {
                    $start = \Carbon\Carbon::parse($data['start_time']);
                    $end = \Carbon\Carbon::parse($data['end_time']);
                    $data['duration_minutes'] = $start->diffInMinutes($end);
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
        if ((int) $sessionLog->therapist_id !== (int) $therapist->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        // Draft -> ok to submit
        if ($sessionLog->isDraft() || $sessionLog->status === null) {
            return $this->repository->submit($sessionLog, $therapist);
        }

        // Already submitted -> validation-style error
        if ($sessionLog->isSubmitted()) {
            throw new \InvalidArgumentException('Session log has already been submitted.');
        }

        // Approved / cancelled -> not allowed
        throw new \InvalidArgumentException('Session log cannot be submitted in its current status.');
    }

    public function approve(User $admin, SessionLog $sessionLog): SessionLog
    {
        if (! $sessionLog->isSubmitted()) {
            throw new \InvalidArgumentException('Session log must be submitted before approval.');
        }

        return $this->repository->approve($sessionLog, $admin);
    }

    public function cancel(User $user, SessionLog $sessionLog, string $reason): SessionLog
    {
        $role = $user->role instanceof Role ? $user->role->value : $user->role;
        $isAdmin = $role === Role::ADMIN->value;

        if (! $isAdmin && $sessionLog->therapist_id !== $user->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        if (! $sessionLog->status->canCancel()) {
            throw new \InvalidArgumentException('Session log cannot be cancelled in its current status.');
        }

        return $this->repository->cancel($sessionLog, $reason);
    }

    private function assertSessionDateWithinSsa(string $sessionDate, \DateTimeInterface $ssaStart, \DateTimeInterface $ssaEnd): void
    {
        $date = Carbon::parse($sessionDate);

        if ($date->lt(Carbon::parse($ssaStart)) || $date->gt(Carbon::parse($ssaEnd))) {
            throw new \InvalidArgumentException('Session date must be within the SSA start and end dates.');
        }
    }

    private function assertDurationWithinServiceBounds(int $durationMinutes, ?int $minDuration, ?int $maxDuration): void
    {
        if ($minDuration !== null && $durationMinutes < $minDuration) {
            throw new \InvalidArgumentException('Session duration is below the service minimum.');
        }

        if ($maxDuration !== null && $durationMinutes > $maxDuration) {
            throw new \InvalidArgumentException('Session duration exceeds the service maximum.');
        }
    }

    private function assertBillingDataComplete(array $billing): void
    {
        // Validate therapist contract exists
        if (! $billing['therapist']['contract_id']) {
            throw new \InvalidArgumentException('Active therapist contract is required for this service and date.');
        }

        // Validate therapist service rate is configured
        if (! $billing['therapist']['rate_type'] || $billing['therapist']['rate_amount'] === null) {
            throw new \InvalidArgumentException('Therapist service rate is not configured for this service in the active contract.');
        }

        // Validate school contract exists
        if (! $billing['school']['contract_id']) {
            throw new \InvalidArgumentException('Active school contract is required for this service and date.');
        }

        // Validate school service rate is configured
        if (! $billing['school']['rate_type'] || $billing['school']['rate_amount'] === null) {
            throw new \InvalidArgumentException('School service rate is not configured for this service in the active contract.');
        }
    }

    private function assertScheduleNotBilled(Schedule $schedule): void
    {
        if ($schedule->billing_status === BillingStatus::BILLED) {
            throw new \InvalidArgumentException('Session logs cannot be added to a billed schedule.');
        }
    }

    private function assertScheduleHasNoLogs(Schedule $schedule): void
    {
        if ($this->repository->getSessionLogsForSchedule($schedule->id)->isNotEmpty()) {
            throw new \InvalidArgumentException('Only one session log can be created per schedule.');
        }
    }

    private function assertSchoolIdPresent(?int $schoolId): void
    {
        if (! $schoolId) {
            throw new \InvalidArgumentException('A school must be associated before creating a session log.');
        }
    }

    /**
     * Set is_billable_* flags on the payload based on the selected SessionOutcome.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyOutcomeBillingFlags(array &$data): void
    {
        $outcomeValue = $data['outcome'] ?? SessionOutcome::SERVICES_ADMINISTERED->value;

        $outcome = SessionOutcome::from($outcomeValue);

        $data['is_billable_therapist'] = $outcome->isBillableForTherapist();
        $data['is_billable_school'] = $outcome->isBillableForSchool();
    }
}
