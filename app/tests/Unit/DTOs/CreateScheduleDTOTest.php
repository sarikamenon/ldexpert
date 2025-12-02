<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateScheduleDTO;
use App\Enums\RecurrenceType;
use Tests\TestCase;

final class CreateScheduleDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_expected_types(): void
    {
        $data = [
            'therapist_id' => 10,
            'ssa_id' => '5',
            'service_id' => '3',
            'student_ids' => ['1', 2],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => '2025-12-31',
            'occurrence_count' => 5,
            'is_group' => '1',
            'notes' => 'Test notes',
        ];

        $dto = CreateScheduleDTO::fromArray($data);

        $this->assertSame(10, $dto->therapistId);
        $this->assertSame(5, $dto->ssaId);
        $this->assertSame(3, $dto->serviceId);
        $this->assertSame([1, 2], $dto->studentIds);
        $this->assertSame('2025-12-01', $dto->scheduleDate);
        $this->assertSame('09:00', $dto->startTime);
        $this->assertSame('10:00', $dto->endTime);
        $this->assertInstanceOf(RecurrenceType::class, $dto->recurrenceType);
        $this->assertSame(RecurrenceType::WEEKLY, $dto->recurrenceType);
        $this->assertSame('2025-12-31', $dto->recurrenceEndDate);
        $this->assertSame(5, $dto->occurrenceCount);
        $this->assertTrue($dto->isGroup);
        $this->assertSame('Test notes', $dto->notes);
    }

    public function test_to_array_serializes_values(): void
    {
        $dto = new CreateScheduleDTO(
            therapistId: 1,
            ssaId: null,
            serviceId: 2,
            studentIds: [3],
            scheduleDate: '2025-01-01',
            startTime: '08:00',
            endTime: '09:00',
            recurrenceType: RecurrenceType::NONE,
            recurrenceEndDate: null,
            isGroup: false,
            occurrenceCount: null,
            notes: null,
            locationDetails: null,
        );

        $array = $dto->toArray();

        $this->assertSame(1, $array['therapist_id']);
        $this->assertNull($array['ssa_id']);
        $this->assertSame(2, $array['service_id']);
        $this->assertSame([3], $array['student_ids']);
        $this->assertSame('2025-01-01', $array['schedule_date']);
        $this->assertSame('08:00', $array['start_time']);
        $this->assertSame('09:00', $array['end_time']);
        $this->assertSame('none', $array['recurrence_type']);
        $this->assertNull($array['recurrence_end_date']);
        $this->assertFalse($array['is_group']);
        $this->assertNull($array['notes']);
        $this->assertNull($array['occurrence_count']);
    }
}
