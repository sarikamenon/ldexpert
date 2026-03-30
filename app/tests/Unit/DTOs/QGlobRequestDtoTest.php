<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\DTOs\RespondQGlobRequestDTO;
use App\Enums\QGlobRequestStatus;
use PHPUnit\Framework\TestCase;

final class QGlobRequestDtoTest extends TestCase
{
    public function test_create_dto_round_trip(): void
    {
        $dto = CreateQGlobRequestDTO::fromArray([
            'requested_by_id' => 5,
            'student_id' => 9,
            'requested_date' => '2026-04-01',
            'requested_time' => '14:30',
            'note' => 'Hello',
        ]);

        self::assertSame(5, $dto->requestedById);
        self::assertSame(9, $dto->studentId);
        self::assertSame('2026-04-01', $dto->requestedDate);
        self::assertSame('14:30', $dto->requestedTime);
        self::assertSame('Hello', $dto->note);

        self::assertSame([
            'requested_by_id' => 5,
            'student_id' => 9,
            'requested_date' => '2026-04-01',
            'requested_time' => '14:30',
            'note' => 'Hello',
        ], $dto->toArray());
    }

    public function test_filter_dto_parses_status(): void
    {
        $dto = QGlobRequestFilterDTO::fromArray([
            'therapist_id' => '3',
            'status' => 'pending',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        self::assertSame(3, $dto->therapistId);
        self::assertSame(QGlobRequestStatus::PENDING, $dto->status);
        self::assertSame('2026-01-01', $dto->dateFrom);
        self::assertSame('2026-01-31', $dto->dateTo);
    }

    public function test_respond_dto(): void
    {
        $dto = RespondQGlobRequestDTO::fromArray([
            'status' => 'approved',
            'admin_response' => 'Done',
            'responded_by_id' => 1,
        ]);

        self::assertSame(QGlobRequestStatus::APPROVED, $dto->status);
        self::assertSame('Done', $dto->adminResponse);
        self::assertSame(1, $dto->respondedById);
    }
}
