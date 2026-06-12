<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;

final class UpdateScheduleDTO
{
    public function __construct(
        public readonly ?int $ssaId,
        public readonly ?int $serviceId,
        /** @var array<int>|null */
        public readonly ?array $studentIds,
        public readonly ?string $scheduleDate,
        public readonly ?string $startTime,
        public readonly ?string $endTime,
        public readonly ?RecurrenceType $recurrenceType,
        public readonly ?string $recurrenceEndDate,
        public readonly ?bool $isGroup,
        public readonly ?string $locationDetails,
        public readonly ?string $notes,
        public readonly ?BillingStatus $billingStatus,
        public readonly ?int $durationMinutes = null,
        /** @var array<int, string>|null */
        public readonly ?array $occurrenceDates = null,
        /** @var array<int, string>|null */
        public readonly ?array $occurrenceStartTimes = null,
        /** @var array<int, string>|null */
        public readonly ?array $occurrenceEndTimes = null,
        public readonly bool $occurrencesRegenerated = false,
        /** 'occurrence' (edit this one only) or 'series' (full recurring editor). */
        public readonly ?string $editScope = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $recurrenceType = null;
        if (isset($data['recurrence_type']) && $data['recurrence_type'] !== '') {
            $recurrenceType = $data['recurrence_type'] instanceof RecurrenceType
                ? $data['recurrence_type']
                : RecurrenceType::from($data['recurrence_type']);
        }

        $billingStatus = null;
        if (isset($data['billing_status']) && $data['billing_status'] !== '') {
            $billingStatus = $data['billing_status'] instanceof BillingStatus
                ? $data['billing_status']
                : BillingStatus::from($data['billing_status']);
        }

        $studentIds = null;
        if (isset($data['student_ids']) && is_array($data['student_ids'])) {
            $studentIds = array_map(
                static fn ($id): int => (int) $id,
                $data['student_ids']
            );
        }

        $occurrenceDates = null;
        if (isset($data['occurrence_dates']) && is_array($data['occurrence_dates']) && count($data['occurrence_dates']) > 0) {
            $occurrenceDates = $data['occurrence_dates'];
        }

        $occurrenceStartTimes = null;
        if (isset($data['occurrence_start_times']) && is_array($data['occurrence_start_times']) && count($data['occurrence_start_times']) > 0) {
            $occurrenceStartTimes = $data['occurrence_start_times'];
        }

        $occurrenceEndTimes = null;
        if (isset($data['occurrence_end_times']) && is_array($data['occurrence_end_times']) && count($data['occurrence_end_times']) > 0) {
            $occurrenceEndTimes = $data['occurrence_end_times'];
        }

        return new self(
            ssaId: isset($data['ssa_id']) && $data['ssa_id'] !== ''
                ? (int) $data['ssa_id']
                : null,
            serviceId: isset($data['service_id']) && $data['service_id'] !== ''
                ? (int) $data['service_id']
                : null,
            studentIds: $studentIds,
            scheduleDate: $data['schedule_date'] ?? null,
            startTime: $data['start_time'] ?? null,
            endTime: $data['end_time'] ?? null,
            recurrenceType: $recurrenceType,
            recurrenceEndDate: isset($data['recurrence_end_date']) && $data['recurrence_end_date'] !== ''
                ? $data['recurrence_end_date']
                : null,
            isGroup: isset($data['is_group']) ? (bool) $data['is_group'] : null,
            locationDetails: isset($data['location_details']) && $data['location_details'] !== ''
                ? $data['location_details']
                : null,
            notes: $data['notes'] ?? null,
            billingStatus: $billingStatus,
            durationMinutes: isset($data['duration_minutes']) && $data['duration_minutes'] !== ''
                ? (int) $data['duration_minutes']
                : null,
            occurrenceDates: $occurrenceDates,
            occurrenceStartTimes: $occurrenceStartTimes,
            occurrenceEndTimes: $occurrenceEndTimes,
            occurrencesRegenerated: (bool) ($data['occurrences_regenerated'] ?? false),
            editScope: isset($data['edit_scope']) && $data['edit_scope'] !== ''
                ? (string) $data['edit_scope']
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = [];

        if ($this->ssaId !== null) {
            $array['ssa_id'] = $this->ssaId;
        }
        if ($this->serviceId !== null) {
            $array['service_id'] = $this->serviceId;
        }
        if ($this->studentIds !== null) {
            $array['student_ids'] = $this->studentIds;
        }
        if ($this->scheduleDate !== null) {
            $array['schedule_date'] = $this->scheduleDate;
        }
        if ($this->startTime !== null) {
            $array['start_time'] = $this->startTime;
        }
        if ($this->endTime !== null) {
            $array['end_time'] = $this->endTime;
        }
        if ($this->durationMinutes !== null) {
            $array['duration_minutes'] = $this->durationMinutes;
        }
        if ($this->recurrenceType !== null) {
            $array['recurrence_type'] = $this->recurrenceType->value;
        }
        if ($this->recurrenceEndDate !== null) {
            $array['recurrence_end_date'] = $this->recurrenceEndDate;
        }
        if ($this->isGroup !== null) {
            $array['is_group'] = $this->isGroup;
        }
        if ($this->locationDetails !== null) {
            $array['location_details'] = $this->locationDetails;
        }
        if ($this->notes !== null) {
            $array['notes'] = $this->notes;
        }
        if ($this->billingStatus !== null) {
            $array['billing_status'] = $this->billingStatus->value;
        }

        return $array;
    }
}
