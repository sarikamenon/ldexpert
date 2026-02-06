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
            'occurrence_dates' => ['2025-12-01', '2025-12-08', '2025-12-15'],
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
        $this->assertSame(['2025-12-01', '2025-12-08', '2025-12-15'], $dto->occurrenceDates);
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
            occurrenceDates: null,
            notes: null,
            locationDetails: null,
            durationMinutes: 60,
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
        $this->assertNull($array['occurrence_dates']);
    }

    public function test_from_array_calculates_end_time_from_duration_minutes(): void
    {
        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
        ];

        $dto = CreateScheduleDTO::fromArray($data);

        $this->assertSame('10:00', $dto->endTime);
        $this->assertSame(60, $dto->durationMinutes);
    }

    public function test_from_array_calculates_duration_minutes_from_end_time(): void
    {
        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'recurrence_type' => 'none',
        ];

        $dto = CreateScheduleDTO::fromArray($data);

        $this->assertSame('10:30', $dto->endTime);
        $this->assertSame(90, $dto->durationMinutes);
    }

    public function test_from_array_throws_exception_when_neither_end_time_nor_duration_provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either end_time or duration_minutes must be provided.');

        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'recurrence_type' => 'none',
        ];

        CreateScheduleDTO::fromArray($data);
    }

    public function test_from_array_throws_exception_when_duration_minutes_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duration_minutes must be greater than 0.');

        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'duration_minutes' => 0,
            'recurrence_type' => 'none',
        ];

        CreateScheduleDTO::fromArray($data);
    }

    public function test_from_array_throws_exception_when_end_time_is_before_start_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('end_time must be after start_time.');

        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '10:00',
            'end_time' => '09:00',
            'recurrence_type' => 'none',
        ];

        CreateScheduleDTO::fromArray($data);
    }

    public function test_from_array_uses_both_when_both_provided(): void
    {
        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration_minutes' => 60,
            'recurrence_type' => 'none',
        ];

        $dto = CreateScheduleDTO::fromArray($data);

        $this->assertSame('10:00', $dto->endTime);
        $this->assertSame(60, $dto->durationMinutes);
    }

    public function test_from_array_throws_exception_when_both_provided_but_conflict(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duration_minutes (120) does not match the duration calculated from start_time and end_time (60). They must be consistent.');

        $data = [
            'therapist_id' => 10,
            'service_id' => '3',
            'student_ids' => ['1'],
            'schedule_date' => '2025-12-01',
            'start_time' => '09:00',
            'end_time' => '10:00', // This suggests 60 minutes
            'duration_minutes' => 120, // But this says 120 minutes - conflict!
            'recurrence_type' => 'none',
        ];

        CreateScheduleDTO::fromArray($data);
    }
}
