<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\BillingStatus;
use App\Enums\RateType;
use App\Enums\Role;
use App\Enums\SessionOutcome;
use App\Mail\SessionLogSentBackMail;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class SessionLogService
{
    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
        private readonly SessionLogRateService $rateService,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SessionLog>
     */
    public function getSessionLogs(User $therapist, array $filters = []): Collection
    {
        return $this->repository->getSessionLogsForTherapist($therapist, $filters);
    }

    /** @return Collection<int, SessionLog> */
    public function getOrphanLogsForCalendar(ScheduleFilterDTO $filters): Collection
    {
        return $this->repository->getOrphanLogsForCalendar($filters);
    }

    public function findForTherapist(User $therapist, int $sessionLogId): ?SessionLog
    {
        return $this->repository->findForTherapist($therapist, $sessionLogId);
    }

    /** @return Collection<int, \App\Models\ServiceSupportAgreement> */
    public function getActiveSSAsForStudent(int $studentId): Collection
    {
        return $this->repository->getActiveSSAsForStudent($studentId);
    }

    /**
     * @param  array<int, int>  $scheduleIds
     * @return Collection<int|string, Collection<int, SessionLog>>
     */
    public function getSessionLogsByScheduleIds(User $therapist, array $scheduleIds): Collection
    {
        return $this->repository->getSessionLogsByScheduleIds($scheduleIds, $therapist);
    }

    public function createFromSchedule(User $therapist, Schedule $schedule, CreateSessionLogDTO $dto): SessionLog
    {
        return DB::transaction(function () use ($therapist, $schedule, $dto): SessionLog {
            // Validate therapist has access to schedule
            if ($schedule->therapist_id !== $therapist->id) {
                throw new \InvalidArgumentException('Therapist does not have access to this schedule.');
            }

            $this->assertScheduleAllowsSessionLogSubmission($schedule);

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
            $schoolId = $ssa->student->studentProfile->school_id ?? $schedule->school_id ?? null;
            $this->assertSchoolIdPresent($schoolId);

            $this->assertScheduleNotBilled($schedule);
            $this->assertScheduleHasNoLogs($schedule);

            $outcome = SessionOutcome::from($dto->outcome);

            // Calculate billing amounts and validate contracts (outcome and private/school drive rate choice)
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes,
                $outcome
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

            // Convert user-local session_date/start_time/end_time to UTC last,
            // so billing/contract lookups above used the local date (correct
            // for "the day the work was done") while storage uses UTC.
            $data = $this->timezoneService->convertSessionLocalToUtc($data, $therapist);

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
            $schoolId = $dto->schoolId ?? $ssa->student->studentProfile->school_id ?? null;
            $this->assertSchoolIdPresent($schoolId);
            $thoMinutes = $ssa->minutes_per_session ?? $dto->thoMinutes;

            $outcome = SessionOutcome::from($dto->outcome);

            // Calculate billing amounts and validate contracts (outcome and private/school drive rate choice)
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id,
                $schoolId,
                $dto->serviceId,
                $dto->sessionDate,
                $dto->durationMinutes,
                $outcome
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

            // Convert user-local session_date/start_time/end_time to UTC last,
            // so billing/contract lookups above used the local date (correct
            // for "the day the work was done") while storage uses UTC.
            $data = $this->timezoneService->convertSessionLocalToUtc($data, $therapist);

            return $this->repository->create($data);
        });
    }

    public function update(User $therapist, SessionLog $sessionLog, UpdateSessionLogDTO $dto): SessionLog
    {
        return DB::transaction(function () use ($therapist, $sessionLog, $dto): SessionLog {
            $isAdmin = $therapist->role->value === 'admin';

            if (! $isAdmin && $sessionLog->therapist_id !== $therapist->id) {
                throw new \InvalidArgumentException('Therapist does not have access to this session log.');
            }

            if (! $isAdmin && ! $sessionLog->canEdit()) {
                throw new \InvalidArgumentException('Session log cannot be edited in its current status.');
            }

            if ($isAdmin && $sessionLog->isAttachedToInvoiceOrTherapistBill()) {
                throw new \InvalidArgumentException(SessionLog::RATE_OVERRIDE_BLOCKED_MESSAGE);
            }

            if ($isAdmin && $sessionLog->isApproved()) {
                throw new \InvalidArgumentException('Approved session logs cannot be edited.');
            }

            $data = $dto->toArray();

            // If outcome is provided, update billing flags accordingly
            if (isset($data['outcome'])) {
                $this->applyOutcomeBillingFlags($data);
            }

            $durationChanged = isset($data['duration_minutes']) || isset($data['start_time']) || isset($data['end_time']);
            $outcomeChanged = isset($data['outcome']) && ($data['outcome'] !== ($sessionLog->outcome->value ?? $sessionLog->getRawOriginal('outcome')));
            $schoolIdChanged = array_key_exists('school_id', $data) && (int) ($data['school_id'] ?? 0) !== (int) $sessionLog->school_id;

            $shouldRecalculateBilling = ($durationChanged || $outcomeChanged || $schoolIdChanged) && ! ($dto->isRateOverride ?? false);

            if ($shouldRecalculateBilling) {
                if ($durationChanged) {
                    if (isset($data['start_time']) && isset($data['end_time'])) {
                        $start = Carbon::parse($data['start_time']);
                        $end = Carbon::parse($data['end_time']);
                        $data['duration_minutes'] = $start->diffInMinutes($end);
                    } elseif (! isset($data['duration_minutes'])) {
                        $data['duration_minutes'] = $sessionLog->calculateDurationMinutes();
                    }
                }

                $sessionDate = $data['session_date'] ?? $sessionLog->session_date->format('Y-m-d');
                $serviceId = $data['service_id'] ?? $sessionLog->service_id;
                $schoolId = $data['school_id'] ?? $sessionLog->school_id;
                $durationMinutes = (int) ($data['duration_minutes'] ?? $sessionLog->duration_minutes);
                $therapistUserId = $isAdmin ? $sessionLog->therapist_id : $therapist->id;
                $outcome = isset($data['outcome'])
                    ? SessionOutcome::from($data['outcome'])
                    : ($sessionLog->outcome ?? SessionOutcome::SERVICES_ADMINISTERED);

                $billing = $this->rateService->calculateDualBilling(
                    $therapistUserId,
                    $schoolId,
                    $serviceId,
                    $sessionDate,
                    $durationMinutes,
                    $outcome
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

            // When admin overrides rates, recalculate billable/invoice amounts from rate type, rate amount, and session duration
            if ($isAdmin && ($dto->isRateOverride ?? false)) {
                $durationMinutes = (int) $sessionLog->duration_minutes;
                if ($durationMinutes > 0 && isset($data['therapist_rate_type'], $data['therapist_rate_amount'])) {
                    $data['therapist_billable_amount'] = $this->rateService->calculateBillableAmount(
                        RateType::from($data['therapist_rate_type']),
                        (float) $data['therapist_rate_amount'],
                        $durationMinutes
                    );
                }
                if ($durationMinutes > 0 && isset($data['school_rate_type'], $data['school_rate_amount'])) {
                    $data['school_invoice_amount'] = $this->rateService->calculateBillableAmount(
                        RateType::from($data['school_rate_type']),
                        (float) $data['school_rate_amount'],
                        $durationMinutes
                    );
                }
            }

            // Convert user-local session_date/start_time/end_time to UTC last,
            // so billing/contract lookups above used the local date (correct
            // for "the day the work was done") while storage uses UTC.
            $rowOwner = $sessionLog->therapist ?? $therapist;
            $data = $this->timezoneService->convertSessionLocalToUtc($data, $rowOwner);

            return $this->repository->update($sessionLog, $data);
        });
    }

    public function submit(User $therapist, SessionLog $sessionLog): SessionLog
    {
        if ((int) $sessionLog->therapist_id !== (int) $therapist->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        // Draft or sent back -> ok to submit
        if ($sessionLog->isDraft() || $sessionLog->isSentBack() || $sessionLog->status === null) {
            return $this->repository->submit($sessionLog, $therapist);
        }

        // Already submitted -> validation-style error
        if ($sessionLog->isSubmitted()) {
            throw new \InvalidArgumentException('Session log has already been submitted.');
        }

        // Approved / cancelled -> not allowed
        throw new \InvalidArgumentException('Session log cannot be submitted in its current status.');
    }

    public function sendBack(User $admin, SessionLog $sessionLog, string $comment): SessionLog
    {
        if (! $sessionLog->isSubmitted()) {
            throw new \InvalidArgumentException('Session log must be submitted before it can be sent back.');
        }

        $sessionLog = $this->repository->sendBack($sessionLog, $admin, $comment);

        $sessionLog->loadMissing(['therapist', 'student', 'service']);
        if ($sessionLog->therapist?->email) {
            try {
                Mail::to($sessionLog->therapist->email)->queue(new SessionLogSentBackMail($sessionLog));
            } catch (\Throwable $e) {
                Log::error('SessionLogService: failed to queue sent-back email', [
                    'session_log_id' => $sessionLog->id,
                    'email' => $sessionLog->therapist->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sessionLog;
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
        $isAdmin = $user->role === Role::ADMIN;

        if (! $isAdmin && $sessionLog->therapist_id !== $user->id) {
            throw new \InvalidArgumentException('Therapist does not have access to this session log.');
        }

        if (! $sessionLog->status?->canCancel()) {
            throw new \InvalidArgumentException('Session log cannot be cancelled in its current status.');
        }

        return $this->repository->cancel($sessionLog, $reason);
    }

    private function assertSessionDateWithinSsa(string $sessionDate, \DateTimeInterface $ssaStart, ?\DateTimeInterface $ssaEnd): void
    {
        $date = Carbon::parse($sessionDate);

        if ($date->lt(Carbon::parse($ssaStart)) || ($ssaEnd !== null && $date->gt(Carbon::parse($ssaEnd)))) {
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

    /**
     * @param  array<string, array<string, mixed>>  $billing
     */
    private function assertBillingDataComplete(array $billing): void
    {
        if (! $billing['therapist']['contract_id']) {
            throw ValidationException::withMessages([
                'session_date' => [
                    'No active therapist contract was found for this date. Please add or activate a therapist contract that covers the session date (Admin → Contracts → Therapist Contracts).',
                ],
            ]);
        }

        if (! $billing['therapist']['rate_type'] || $billing['therapist']['rate_amount'] === null) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'The therapist rate for this service is not set. Please configure the service rate in the therapist contract (Admin → Contracts → Therapist Contracts).',
                ],
            ]);
        }

        if (! $billing['school']['contract_id']) {
            throw ValidationException::withMessages([
                'session_date' => [
                    'No active school/family contract was found for this date. Please add or activate a school/family contract that covers the session date (Admin → Contracts → School Contracts).',
                ],
            ]);
        }

        if (! $billing['school']['rate_type'] || $billing['school']['rate_amount'] === null) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'The school/family rate for this service is not set. Please configure the service rate in the school/family contract (Admin → Contracts → School Contracts).',
                ],
            ]);
        }
    }

    private function assertScheduleNotBilled(Schedule $schedule): void
    {
        if ($schedule->billing_status === BillingStatus::BILLED) {
            throw new \InvalidArgumentException('Session logs cannot be added to a billed schedule.');
        }
    }

    private function assertScheduleAllowsSessionLogSubmission(Schedule $schedule): void
    {
        if (! $schedule->is_billable) {
            throw new \InvalidArgumentException(
                'Session logs cannot be submitted for schedules excluded from the Past Sessions queue for this organization.'
            );
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
            throw new \InvalidArgumentException('A school/family must be associated before creating a session log.');
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
