<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateSessionLogDTO;
use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Enums\ServiceStatus;
use App\Enums\SessionLogImportRowStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLogImport;
use App\Models\SessionLogImportRow;
use App\Models\User;
use Carbon\Carbon;

final class SessionLogImportRowProcessor
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly SessionLogRepositoryInterface $sessionLogRepository,
        private readonly SessionLogRateService $rateService,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    /**
     * @param  array<string, string>  $mappedData
     * @param  array<string, bool>  $alreadyProcessed
     * @param  array<string, bool>  $currentBatch
     */
    public function process(
        SessionLogImport $import,
        SessionLogImportRow $importRow,
        array $mappedData,
        array &$alreadyProcessed,
        array &$currentBatch,
    ): void {
        $importRow->update(['status' => SessionLogImportRowStatus::PROCESSING, 'raw_data' => $mappedData]);

        try {
            $this->processRowData($import, $importRow, $mappedData, $alreadyProcessed, $currentBatch);
        } catch (\Exception $e) {
            $importRow->update([
                'status' => SessionLogImportRowStatus::VALIDATION_ERROR,
                'error_message' => 'Failed to process row: '.$e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $mappedData
     * @param  array<string, bool>  $alreadyProcessed
     * @param  array<string, bool>  $currentBatch
     */
    private function processRowData(
        SessionLogImport $import,
        SessionLogImportRow $importRow,
        array $mappedData,
        array &$alreadyProcessed,
        array &$currentBatch,
    ): void {
        // Skip criteria
        $referenceId = trim($mappedData['reference_id'] ?? '');
        if ($referenceId === '') {
            $this->markSkipped($importRow, 'Empty reference ID (Entry_KEYID).');

            return;
        }

        $errorFlag = strtoupper(trim($mappedData['error_flag'] ?? ''));
        $errorDetails = strtolower(trim($mappedData['error_details'] ?? ''));
        if ($errorFlag !== 'N' && $errorDetails !== 'no therapist selected') {
            $this->markSkipped($importRow, 'Row flagged with error by source system.');

            return;
        }

        // Duplicate check
        if (isset($alreadyProcessed[$referenceId]) || isset($currentBatch[$referenceId])) {
            $importRow->update([
                'status' => SessionLogImportRowStatus::DUPLICATE,
                'reference_id' => $referenceId,
                'error_message' => "Duplicate reference ID: {$referenceId}",
                'processed_at' => now(),
            ]);

            return;
        }
        $currentBatch[$referenceId] = true;
        $importRow->update(['reference_id' => $referenceId]);

        // Lookups
        $school = $this->lookupSchool($mappedData['school_name'] ?? '');
        if (! $school) {
            $this->markError($importRow, "School/family not found by external EMR name: '{$mappedData['school_name']}'.");

            return;
        }

        $student = $this->lookupStudent($mappedData['student_id_number'] ?? '', $school->id);
        if (! $student) {
            $this->markError($importRow, "Student not found with ID '{$mappedData['student_id_number']}' at school/family '{$school->name}'.");

            return;
        }

        $therapist = $this->lookupTherapist($mappedData['therapist_email'] ?? '');
        if (! $therapist) {
            $this->markError($importRow, "Therapist not found or inactive: '{$mappedData['therapist_email']}'.");

            return;
        }

        $service = $this->lookupService($mappedData['primary_service_name'] ?? '');
        if (! $service) {
            $this->markError($importRow, "Service not found: '{$mappedData['primary_service_name']}'.");

            return;
        }

        // Parse & validate data
        $sessionDate = $this->parseDate($mappedData['session_date'] ?? '');
        if (! $sessionDate) {
            $this->markError($importRow, "Invalid date format: '{$mappedData['session_date']}'.");

            return;
        }
        if (Carbon::parse($sessionDate)->isFuture()) {
            $this->markError($importRow, 'Session date cannot be in the future.');

            return;
        }

        $outcome = $this->mapOutcome($mappedData['type1'] ?? '');
        if (! $outcome) {
            $this->markError($importRow, "Unknown outcome Type1: '{$mappedData['type1']}'.");

            return;
        }

        $hours = (float) ($mappedData['hours'] ?? 0);
        $durationMinutes = (int) round($hours * 60);
        if ($durationMinutes <= 0) {
            $this->markError($importRow, 'Duration must be greater than zero.');

            return;
        }

        $startTime = $this->parseTime($mappedData['start_time'] ?? '');
        $endTime = $this->parseTime($mappedData['end_time'] ?? '');
        if (! $startTime || ! $endTime) {
            $this->markError($importRow, 'Invalid start or end time format.');

            return;
        }

        // SSA lookup
        $ssa = $this->lookupSSA($student->id, $service->id, $sessionDate, $therapist->id);
        if (! $ssa) {
            $this->markError($importRow, 'No matching SSA found for student + service + date + therapist.');

            return;
        }

        // Billing
        try {
            $billing = $this->rateService->calculateDualBilling(
                $therapist->id, $school->id, $service->id, $sessionDate, $durationMinutes, $outcome
            );
        } catch (\Exception $e) {
            $this->markError($importRow, 'Billing error: '.$e->getMessage());

            return;
        }

        // Build notes
        $notes = $this->buildNotes($mappedData['notes'] ?? '', $mappedData['goals_progress'] ?? '');

        // Determine group flag
        $numStudents = (int) ($mappedData['num_students'] ?? 1);
        $isGroup = $numStudents > 1;

        // Create session log
        $dto = CreateSessionLogDTO::fromArray([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'school_id' => $school->id,
            'schedule_id' => null,
            'session_date' => $sessionDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'tho_minutes' => $ssa->minutes_per_session,
            'notes' => $notes,
            'outcome' => $outcome->value,
            'is_billable_therapist' => $outcome->isBillableForTherapist(),
            'therapist_contract_id' => $billing['therapist']['contract_id'],
            'therapist_rate_type' => $billing['therapist']['rate_type'],
            'therapist_rate_amount' => $billing['therapist']['rate_amount'],
            'therapist_billable_amount' => $billing['therapist']['billable_amount'],
            'is_billable_school' => $outcome->isBillableForSchool(),
            'school_contract_id' => $billing['school']['contract_id'],
            'school_rate_type' => $billing['school']['rate_type'],
            'school_rate_amount' => $billing['school']['rate_amount'],
            'school_invoice_amount' => $billing['school']['invoice_amount'],
            'is_rate_override' => false,
            'override_reason' => null,
            'cancellation_reason' => null,
        ]);

        $logData = $dto->toArray();
        $logData['status'] = SessionLogStatus::APPROVED->value;
        $logData['submitted_at'] = now();
        $logData['submitted_by_id'] = $import->user_id;
        $logData['approved_at'] = now();
        $logData['approved_by_id'] = $import->user_id;
        $logData['delivery_mode'] = 'virtual';
        $logData['is_group'] = $isGroup;

        // CSV rows are in the importing therapist's local TZ. Convert to UTC
        // for storage (per CLAUDE.md: all session_log timestamps stored UTC).
        $logData = $this->convertLogLocalToUtc($logData, $therapist);

        $sessionLog = $this->sessionLogRepository->create($logData);

        // Increment SSA served_minutes
        if ($outcome->shouldIncludeInTho()) {
            $ssa->increment('served_minutes', $durationMinutes);
        }

        $importRow->update([
            'status' => SessionLogImportRowStatus::DONE,
            'session_log_id' => $sessionLog->id,
            'processed_at' => now(),
        ]);
    }

    /**
     * Convert user-local session_date/start_time/end_time on a log payload
     * to UTC, using the therapist's TZ. Mirrors
     * SessionLogService::convertLocalDateTimesToUtc — duplicated here to
     * keep the processor independent of the service constructor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function convertLogLocalToUtc(array $data, User $therapist): array
    {
        $sessionDateLocal = $data['session_date'] ?? null;
        $startLocal = $data['start_time'] ?? null;
        $endLocal = $data['end_time'] ?? null;

        if (! is_string($sessionDateLocal) || $sessionDateLocal === '') {
            return $data;
        }

        if (is_string($startLocal) && $startLocal !== '') {
            $startStr = str_contains($startLocal, ' ')
                ? $startLocal
                : $sessionDateLocal.' '.$startLocal;
            $startUtc = $this->timezoneService->parseUserLocalToUtc($startStr, $therapist);
            $data['start_time'] = $startUtc->format('Y-m-d H:i:s');
            $data['session_date'] = $startUtc->format('Y-m-d');
        }

        if (is_string($endLocal) && $endLocal !== '') {
            $endStr = str_contains($endLocal, ' ')
                ? $endLocal
                : $sessionDateLocal.' '.$endLocal;
            $endUtc = $this->timezoneService->parseUserLocalToUtc($endStr, $therapist);
            $data['end_time'] = $endUtc->format('Y-m-d H:i:s');
        }

        return $data;
    }

    public function lookupSchool(string $name): ?School
    {
        if ($name === '') {
            return null;
        }
        $school = $this->schoolRepository->findByExternalEmrName($name);
        if ($school && $school->status === SchoolStatus::ACTIVE) {
            return $school;
        }

        return null;
    }

    public function lookupStudent(string $idNumber, int $schoolId): ?User
    {
        if ($idNumber === '') {
            return null;
        }
        $profile = $this->studentRepository->findByIdNumber($idNumber, $schoolId);
        if ($profile && $profile->user && $profile->user->status === UserStatus::ACTIVE) {
            return $profile->user;
        }

        return null;
    }

    public function lookupTherapist(string $email): ?User
    {
        if ($email === '') {
            return null;
        }
        $user = $this->userRepository->findByEmail($email);
        if ($user && $user->role === Role::THERAPIST && $user->status === UserStatus::ACTIVE) {
            return $user;
        }

        return null;
    }

    public function lookupService(string $name): ?Service
    {
        if ($name === '') {
            return null;
        }

        return Service::query()
            ->where('name', $name)
            ->where('status', ServiceStatus::ACTIVE)
            ->first();
    }

    public function lookupSSA(int $studentId, int $serviceId, string $sessionDate, int $therapistId): ?ServiceSupportAgreement
    {
        return ServiceSupportAgreement::query()
            ->where('student_id', $studentId)
            ->where('primary_service_id', $serviceId)
            ->where('assigned_therapist_id', $therapistId)
            ->where('start_date', '<=', $sessionDate)
            ->where('end_date', '>=', $sessionDate)
            ->whereIn('status', [SSAStatus::ACTIVE, SSAStatus::PENDING])
            ->orderByRaw("FIELD(status, 'active', 'pending')")
            ->orderByDesc('start_date')
            ->first();
    }

    public function mapOutcome(string $type1): ?SessionOutcome
    {
        return match (strtoupper(trim($type1))) {
            'SA' => SessionOutcome::SERVICES_ADMINISTERED,
            'NS' => SessionOutcome::NO_SHOW,
            'BC' => SessionOutcome::BILLABLE_CANCELLATION,
            'NBC1' => SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT,
            'NBC2' => SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER,
            default => null,
        };
    }

    public function buildNotes(string $narrative, string $goalsProgress): string
    {
        $parts = array_filter([trim($narrative), trim($goalsProgress)]);
        if (count($parts) === 2) {
            return $parts[0]."\n\n--- Goals Progress ---\n".$parts[1];
        }

        return implode('', $parts);
    }

    public function parseDate(string $date): ?string
    {
        try {
            return Carbon::createFromFormat('m/d/Y', trim($date))?->format('Y-m-d');
        } catch (\Exception) {
            try {
                return Carbon::parse(trim($date))->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }
    }

    public function parseTime(string $time): ?string
    {
        try {
            return Carbon::createFromFormat('g:i A', trim($time))?->format('H:i:s');
        } catch (\Exception) {
            try {
                return Carbon::parse(trim($time))->format('H:i:s');
            } catch (\Exception) {
                return null;
            }
        }
    }

    private function markSkipped(SessionLogImportRow $row, string $message): void
    {
        $row->update([
            'status' => SessionLogImportRowStatus::SKIPPED,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }

    private function markError(SessionLogImportRow $row, string $message): void
    {
        $row->update([
            'status' => SessionLogImportRowStatus::VALIDATION_ERROR,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }
}
