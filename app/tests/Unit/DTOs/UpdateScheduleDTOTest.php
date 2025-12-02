<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use PHPUnit\Framework\TestCase;

final class UpdateScheduleDTOTest extends TestCase
{
    public function test_from_array_handles_optional_fields_and_enum_conversion(): void
    {
        $data = [
            'ssa_id' => '5',
            'service_id' => '3',
            'student_ids' => ['1', '2'],
            'schedule_date' => '2025-02-01',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'monthly',
            'recurrence_end_date' => '2025-05-01',
            'is_group' => '1',
            'notes' => 'Updated notes',
            'billing_status' => 'billed',
        ];

        $dto = UpdateScheduleDTO::fromArray($data);

        $this->assertSame(5, $dto->ssaId);
        $this->assertSame(3, $dto->serviceId);
        $this->assertSame([1, 2], $dto->studentIds);
        $this->assertSame('2025-02-01', $dto->scheduleDate);
        $this->assertSame('10:00', $dto->startTime);
        $this->assertSame('11:00', $dto->endTime);
        $this->assertInstanceOf(RecurrenceType::class, $dto->recurrenceType);
        $this->assertSame(RecurrenceType::MONTHLY, $dto->recurrenceType);
        $this->assertSame('2025-05-01', $dto->recurrenceEndDate);
        $this->assertTrue($dto->isGroup);
        $this->assertSame('Updated notes', $dto->notes);
        $this->assertInstanceOf(BillingStatus::class, $dto->billingStatus);
        $this->assertSame(BillingStatus::BILLED, $dto->billingStatus);
    }

    public function test_to_array_includes_only_non_null_values(): void
    {
        $dto = new UpdateScheduleDTO(
            ssaId: null,
            serviceId: 3,
            studentIds: [1],
            scheduleDate: null,
            startTime: '09:00',
            endTime: null,
            recurrenceType: RecurrenceType::NONE,
            recurrenceEndDate: null,
            isGroup: null,
            locationDetails: null,
            notes: null,
            billingStatus: BillingStatus::PENDING,
        );

        $array = $dto->toArray();

        $this->assertArrayNotHasKey('ssa_id', $array);
        $this->assertSame(3, $array['service_id']);
        $this->assertSame([1], $array['student_ids']);
        $this->assertArrayNotHasKey('schedule_date', $array);
        $this->assertSame('09:00', $array['start_time']);
        $this->assertArrayNotHasKey('end_time', $array);
        $this->assertSame('none', $array['recurrence_type']);
        $this->assertArrayNotHasKey('recurrence_end_date', $array);
        $this->assertArrayNotHasKey('is_group', $array);
        $this->assertSame('pending', $array['billing_status']);
    }
}
