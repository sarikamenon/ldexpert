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
        public readonly ?string $notes,
        public readonly ?string $locationDetails,
        public readonly int $durationMinutes = 0,
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
        $endTime = $data['end_time'] ?? null;
        $durationMinutes = isset($data['duration_minutes'])
            ? (int) $data['duration_minutes']
            : ($endTime ? (int) Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime)) : 0);

        return new self(
            therapistId: (int) $data['therapist_id'],
            ssaId: isset($data['ssa_id']) && $data['ssa_id'] !== ''
                ? (int) $data['ssa_id']
                : null,
            serviceId: (int) $data['service_id'],
            studentIds: $studentIds,
            scheduleDate: $data['schedule_date'],
            startTime: $startTime,
            endTime: $endTime ?? Carbon::parse($startTime)->addMinutes($durationMinutes)->toTimeString(),
            recurrenceType: $recurrenceType,
            recurrenceEndDate: isset($data['recurrence_end_date']) && $data['recurrence_end_date'] !== ''
                ? $data['recurrence_end_date']
                : null,
            isGroup: isset($data['is_group']) ? (bool) $data['is_group'] : false,
            occurrenceCount: isset($data['occurrence_count']) && $data['occurrence_count'] !== ''
                ? (int) $data['occurrence_count']
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
            'notes' => $this->notes,
            'location_details' => $this->locationDetails,
        ];
    }
}
