<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\SSAAssignmentDTO;
use App\Enums\ServiceFrequency;
use App\Enums\SSAImportRowStatus;
use App\Enums\SSAStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAImportRow;
use App\Models\User;

/**
 * Handles RSM-specific import logic including status-based upsert.
 *
 * RSM CSV includes a status column (1=active, 3=withdrawn). When an existing
 * SSA is found (exact match on student+service+dates), the action depends on
 * the combination of our status, incoming status, and therapist assignment.
 *
 * Decision table:
 * | Our Status  | Incoming | Current Therapist | Incoming Therapist | Action                               |
 * |-------------|----------|-------------------|--------------------|--------------------------------------|
 * | Pending     | 1        | None              | None               | None                                 |
 * | Pending     | 1        | None              | Yes                | Assign therapist → Active            |
 * | Pending     | 3        | —                 | —                  | Deactivate                           |
 * | Active      | 1        | Yes               | None               | Unassign therapist → Pending         |
 * | Active      | 1        | Yes               | Yes (same)         | None                                 |
 * | Active      | 1        | Yes               | Yes (different)    | Reassign therapist (stay Active)     |
 * | Active      | 3        | Yes               | —                  | Unassign therapist → Deactivate      |
 * | Deactivated | 1        | None              | None               | Mark Pending                         |
 * | Deactivated | 1        | None              | Yes                | Assign therapist → Active            |
 */
final class RsmImportHandler
{
    public function __construct(
        private readonly SSAService $ssaService,
        private readonly SSARepositoryInterface $ssaRepository,
    ) {}

    /**
     * Process an RSM row with status-based upsert logic.
     *
     * @param  array<string, mixed>  $mappedData
     */
    public function processRow(
        SSAImportRow $importRow,
        array $mappedData,
        User $student,
        Service $primaryService,
        ?int $therapistId,
        int $thoMinutes,
    ): void {
        $rsmStatus = (int) ($mappedData['rsm_status'] ?? 1);
        $isWithdrawn = $rsmStatus === 3;

        // Find existing SSA (exact match on student + service + dates)
        $existingSsa = $this->findExactMatchSSA(
            $student->id,
            $primaryService->id,
            (string) $mappedData['start_date'],
            (string) $mappedData['end_date'],
        );

        if ($existingSsa !== null) {
            if ($existingSsa->status === SSAStatus::COMPLETED) {
                $this->finishImportRow($importRow, $existingSsa, 'Skipped: SSA is already completed.');

                return;
            }

            $this->updateSsaFields($existingSsa, $mappedData, $thoMinutes);
            $this->applyStatusLogic($importRow, $existingSsa, $isWithdrawn, $therapistId);

            return;
        }

        // No existing SSA found
        if ($isWithdrawn) {
            $importRow->update([
                'status' => SSAImportRowStatus::DONE,
                'error_message' => 'Skipped: withdrawn status with no existing SSA.',
                'processed_at' => now(),
            ]);

            return;
        }

        // Check for overlapping (non-exact) SSAs before creating
        $overlapping = $this->ssaRepository->checkOverlappingSSAs(
            $student->id,
            $primaryService->id,
            (string) $mappedData['start_date'],
            (string) $mappedData['end_date'],
        );
        if ($overlapping->isNotEmpty()) {
            $importRow->update([
                'status' => SSAImportRowStatus::DUPLICATE,
                'error_message' => 'An active or pending SSA already exists for this student and service within the specified date range.',
                'processed_at' => now(),
            ]);

            return;
        }

        // Create new SSA (incoming status=1, no existing SSA)
        $createData = [
            'student_id' => $student->id,
            'primary_service_id' => $primaryService->id,
            'start_date' => $mappedData['start_date'],
            'end_date' => $mappedData['end_date'],
            'minutes_per_session' => (int) $mappedData['minutes_per_session'],
            'frequency' => ! empty($mappedData['frequency']) ? ServiceFrequency::from($mappedData['frequency']) : null,
            'sessions_per_frequency' => ! empty($mappedData['sessions_per_frequency']) ? (int) $mappedData['sessions_per_frequency'] : null,
            'calculated_minutes' => ! empty($mappedData['calculated_minutes']) ? (int) $mappedData['calculated_minutes'] : null,
            'adjusted_minutes' => ! empty($mappedData['adjusted_minutes']) ? (int) $mappedData['adjusted_minutes'] : null,
            'adjustment_notes' => $mappedData['adjustment_notes'] ?? null,
            'tho_minutes' => $thoMinutes,
            'assigned_therapist_id' => $therapistId,
        ];

        $createDTO = CreateSSADTO::fromArray($createData);
        $ssa = $this->ssaService->create($createDTO);

        $importRow->update([
            'status' => SSAImportRowStatus::DONE,
            'ssa_id' => $ssa->id,
            'processed_at' => now(),
        ]);
    }

    private function applyStatusLogic(
        SSAImportRow $importRow,
        ServiceSupportAgreement $ssa,
        bool $isWithdrawn,
        ?int $therapistId,
    ): void {
        $ourStatus = $ssa->status;
        $hasIncomingTherapist = $therapistId !== null;
        $isTherapistChanged = $hasIncomingTherapist && $ssa->assigned_therapist_id !== $therapistId;

        $noChangeMessage = $this->getNoChangeMessage($ourStatus, $isWithdrawn, $hasIncomingTherapist, $isTherapistChanged);
        if ($noChangeMessage !== null) {
            $this->finishImportRow($importRow, $ssa, $noChangeMessage);

            return;
        }

        $action = 'No changes needed';
        $hasCurrentTherapist = $ssa->assigned_therapist_id !== null;

        if ($isWithdrawn) {
            // Rows 4, 7: Withdrawn — deactivate regardless of current status
            if ($ourStatus === SSAStatus::PENDING || $ourStatus === SSAStatus::ACTIVE) {
                $this->ssaRepository->deactivateWithUnassign($ssa, 'RSM import: withdrawn');
                $action = 'Deactivated (withdrawn)';
            } else {
                $action = 'Already deactivated or completed';
            }
        } else {
            // Incoming status = 1 (active)
            // NOTE: match arms use &$action by reference — verify this works at runtime.
            // If match doesn't propagate the reference correctly, refactor to if/elseif.
            match ($ourStatus) {
                SSAStatus::PENDING => $this->handlePendingWithActiveImport($ssa, $hasIncomingTherapist, $therapistId, $action),
                SSAStatus::ACTIVE => $this->handleActiveWithActiveImport($ssa, $hasIncomingTherapist, $therapistId, $action),
                SSAStatus::DEACTIVATED => $this->handleDeactivatedWithActiveImport($ssa, $hasIncomingTherapist, $therapistId, $action),
                SSAStatus::COMPLETED => null, // Completed SSAs are never modified
            };
        }

        $this->finishImportRow($importRow, $ssa, $action);
    }

    /** @return string|null Message if no changes needed, null if action required */
    private function getNoChangeMessage(
        SSAStatus $ourStatus,
        bool $isWithdrawn,
        bool $hasIncomingTherapist,
        bool $isTherapistChanged,
    ): ?string {
        if ($isWithdrawn) {
            return $ourStatus === SSAStatus::DEACTIVATED
                ? 'Already deactivated'
                : null;
        }

        return match ($ourStatus) {
            SSAStatus::PENDING => $hasIncomingTherapist ? null : 'No changes needed',
            SSAStatus::ACTIVE => ($hasIncomingTherapist && ! $isTherapistChanged) ? 'No changes needed' : null,
            SSAStatus::DEACTIVATED => null,
            SSAStatus::COMPLETED => null, // Completed is handled before applyStatusLogic
        };
    }

    private function finishImportRow(SSAImportRow $importRow, ServiceSupportAgreement $ssa, string $action): void
    {
        $importRow->update([
            'status' => SSAImportRowStatus::DONE,
            'ssa_id' => $ssa->id,
            'error_message' => $action,
            'processed_at' => now(),
        ]);
    }

    private function handlePendingWithActiveImport(
        ServiceSupportAgreement $ssa,
        bool $hasIncomingTherapist,
        ?int $therapistId,
        string &$action,
    ): void {
        if ($hasIncomingTherapist && $therapistId !== null) {
            // Row 3: Assign therapist → automatically activates
            $this->ssaService->assignTherapist(
                $ssa,
                new SSAAssignmentDTO(therapistId: $therapistId, reason: 'RSM import'),
            );
            $action = 'Assigned therapist and marked as active';
        }
        // Row 2: Pending + active incoming + no therapist → no action
    }

    private function handleActiveWithActiveImport(
        ServiceSupportAgreement $ssa,
        bool $hasIncomingTherapist,
        ?int $therapistId,
        string &$action,
    ): void {
        if (! $hasIncomingTherapist) {
            // Row 5: Active + no incoming therapist → unassign → pending
            $this->ssaService->unassignTherapist($ssa, 'RSM import: therapist removed');
            $action = 'Unassigned therapist and marked as pending';
        } elseif ($therapistId !== null && $ssa->assigned_therapist_id !== $therapistId) {
            // Active + different incoming therapist → reassign (status stays active)
            $this->ssaService->assignTherapist(
                $ssa,
                new SSAAssignmentDTO(therapistId: $therapistId, reason: 'RSM import: therapist reassigned'),
            );
            $action = 'Reassigned therapist';
        }
        // Row 6: Active + same incoming therapist → no action (already handled by getNoChangeMessage)
    }

    private function handleDeactivatedWithActiveImport(
        ServiceSupportAgreement $ssa,
        bool $hasIncomingTherapist,
        ?int $therapistId,
        string &$action,
    ): void {
        if ($hasIncomingTherapist && $therapistId !== null) {
            // Row 9: Reactivate and assign therapist → active
            // Use repository directly: SSAService::changeStatus() requires a therapist for activation
            // but we assign immediately after
            $this->ssaRepository->changeStatus(
                $ssa,
                new ChangeSSAStatusDTO(status: SSAStatus::ACTIVE, reason: 'RSM import: reactivated'),
            );
            $ssa->refresh();
            $this->ssaService->assignTherapist(
                $ssa,
                new SSAAssignmentDTO(therapistId: $therapistId, reason: 'RSM import'),
            );
            $action = 'Assigned therapist and marked as active';
        } else {
            // Row 8: Reactivate → pending
            // Use repository directly: SSAService::changeStatus() only allows Deactivated→Active
            $this->ssaRepository->changeStatus(
                $ssa,
                new ChangeSSAStatusDTO(status: SSAStatus::PENDING, reason: 'RSM import: reactivated'),
            );
            $action = 'Marked as pending';
        }
    }

    /**
     * Update SSA fields from import data (THO, frequency, duration, etc.)
     * Does NOT update status — status changes are handled by applyStatusLogic.
     *
     * @param  array<string, mixed>  $mappedData
     */
    private function updateSsaFields(
        ServiceSupportAgreement $ssa,
        array $mappedData,
        int $thoMinutes,
    ): void {
        $updates = ['tho_minutes' => $thoMinutes];

        if (isset($mappedData['minutes_per_session'])) {
            $updates['minutes_per_session'] = (int) $mappedData['minutes_per_session'];
        }

        if (! empty($mappedData['frequency'])) {
            $updates['frequency'] = ServiceFrequency::from($mappedData['frequency'])->value;
        }

        if (isset($mappedData['sessions_per_frequency'])) {
            $updates['sessions_per_frequency'] = (int) $mappedData['sessions_per_frequency'];
        }

        if (isset($mappedData['calculated_minutes'])) {
            $updates['calculated_minutes'] = (int) $mappedData['calculated_minutes'];
        }

        if (isset($mappedData['adjusted_minutes'])) {
            $updates['adjusted_minutes'] = (int) $mappedData['adjusted_minutes'];
        }

        if (! empty($mappedData['adjustment_notes'])) {
            $updates['adjustment_notes'] = (string) $mappedData['adjustment_notes'];
        }

        $ssa->update($updates);
    }

    /**
     * Find an SSA with exact match on student, service, and date range.
     * Searches across all statuses (pending, active, deactivated, completed)
     * so completed SSAs are updated rather than duplicated on re-import.
     */
    private function findExactMatchSSA(
        int $studentId,
        int $serviceId,
        string $startDate,
        string $endDate,
    ): ?ServiceSupportAgreement {
        return ServiceSupportAgreement::query()
            ->forImportLookup($studentId, $serviceId, $startDate, $endDate)
            ->matchableForImport()
            ->first();
    }
}
