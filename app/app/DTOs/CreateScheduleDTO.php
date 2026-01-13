<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RecurrenceType;
use Carbon\Carbon;

final class CreateScheduleDTO
{
    public function __construct(
        public readonly int $therapistId,
        public readonly ?int $ssaId,
        public readonly int $serviceId,
        public readonly array $studentIds,
        public readonly string $scheduleDate,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly RecurrenceType $recurrenceType,
        public readonly ?string $recurrenceEndDate,
        public readonly bool $isGroup,
        public readonly ?int $occurrenceCount,
        public readonly ?array $occurrenceDates,
        public readonly ?string $notes,
        public readonly ?string $locationDetails,
        public readonly int $durationMinutes,
    ) {}

    public static function fromArray(array $data): self
    {
        $recurrenceType = $data['recurrence_type'] instanceof RecurrenceType
            ? $data['recurrence_type']
            : RecurrenceType::from($data['recurrence_type']);

        $studentIds = array_map(
            static fn ($id): int => (int) $id,
            $data['student_ids']
        );

        $startTime = $data['start_time'];
        $endTime = isset($data['end_time']) && $data['end_time'] !== ''
            ? $data['end_time']
            : null;
        $providedDurationMinutes = isset($data['duration_minutes']) && $data['duration_minutes'] !== ''
            ? (int) $data['duration_minutes']
            : null;

        // Calculate missing values: at least one of end_time or duration_minutes must be provided
        $durationMinutes = null;

        if ($providedDurationMinutes !== null && $endTime === null) {
            // duration_minutes provided, calculate end_time
            if ($providedDurationMinutes <= 0) {
                throw new \InvalidArgumentException('duration_minutes must be greater than 0.');
            }
            $durationMinutes = $providedDurationMinutes;
            $endTime = Carbon::parse($startTime)->addMinutes($durationMinutes)->format('H:i');
        } elseif ($endTime !== null && $providedDurationMinutes === null) {
            // end_time provided, calculate duration_minutes
            $durationMinutes = (int) Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
            if ($durationMinutes <= 0) {
                throw new \InvalidArgumentException('end_time must be after start_time.');
            }
        } elseif ($providedDurationMinutes === null && $endTime === null) {
            // Neither provided - this is invalid, throw exception
            throw new \InvalidArgumentException('Either end_time or duration_minutes must be provided.');
        } else {
            // Both provided - validate both are valid and consistent
            // At this point, both are guaranteed to be non-null due to the if-elseif-elseif logic above
            // but we add explicit checks for defensive programming
            if ($providedDurationMinutes === null) {
                throw new \InvalidArgumentException('duration_minutes must be provided.');
            }
            if ($providedDurationMinutes <= 0) {
                throw new \InvalidArgumentException('duration_minutes must be greater than 0.');
            }
            if ($endTime === null) {
                throw new \InvalidArgumentException('end_time must be provided.');
            }
            $calculatedDuration = (int) Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
            if ($calculatedDuration <= 0) {
                throw new \InvalidArgumentException('end_time must be after start_time.');
            }
            // Validate that provided duration_minutes matches the calculated duration from time range
            if ($calculatedDuration !== $providedDurationMinutes) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'duration_minutes (%d) does not match the duration calculated from start_time and end_time (%d). They must be consistent.',
                        $providedDurationMinutes,
                        $calculatedDuration
                    )
                );
            }
            // Use provided duration_minutes value (validated to match calculated duration)
            $durationMinutes = $providedDurationMinutes;
        }

        // Final validation: ensure durationMinutes is set and is a positive integer
        // This provides explicit type safety guarantee before passing to constructor
        if ($durationMinutes === null) {
            throw new \InvalidArgumentException('duration_minutes must be calculated or provided.');
        }
        if (! is_int($durationMinutes) || $durationMinutes <= 0) {
            throw new \InvalidArgumentException('duration_minutes must be a positive integer.');
        }

        // Final validation: ensure endTime is always a string (non-null)
        // This provides explicit type safety guarantee before passing to constructor
        if ($endTime === null || ! is_string($endTime)) {
            throw new \InvalidArgumentException('end_time must be provided or calculated.');
        }

        return new self(
            therapistId: (int) $data['therapist_id'],
            ssaId: isset($data['ssa_id']) && $data['ssa_id'] !== ''
                ? (int) $data['ssa_id']
                : null,
            serviceId: (int) $data['service_id'],
            studentIds: $studentIds,
            scheduleDate: $data['schedule_date'],
            startTime: $startTime,
            endTime: $endTime,
            recurrenceType: $recurrenceType,
            recurrenceEndDate: isset($data['recurrence_end_date']) && $data['recurrence_end_date'] !== ''
                ? $data['recurrence_end_date']
                : null,
            isGroup: isset($data['is_group']) ? (bool) $data['is_group'] : false,
            occurrenceCount: isset($data['occurrence_count']) && $data['occurrence_count'] !== ''
                ? (int) $data['occurrence_count']
                : null,
            occurrenceDates: isset($data['occurrence_dates']) && is_array($data['occurrence_dates']) && count($data['occurrence_dates']) > 0
                ? $data['occurrence_dates']
                : null,
            notes: $data['notes'] ?? null,
            locationDetails: isset($data['location_details']) && $data['location_details'] !== ''
                ? $data['location_details']
                : null,
            durationMinutes: $durationMinutes,
        );
    }

    public function toArray(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'ssa_id' => $this->ssaId,
            'service_id' => $this->serviceId,
            'student_ids' => $this->studentIds,
            'schedule_date' => $this->scheduleDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
            'recurrence_type' => $this->recurrenceType->value,
            'recurrence_end_date' => $this->recurrenceEndDate,
            'is_group' => $this->isGroup,
            'occurrence_count' => $this->occurrenceCount,
            'occurrence_dates' => $this->occurrenceDates,
            'notes' => $this->notes,
            'location_details' => $this->locationDetails,
        ];
    }
}
