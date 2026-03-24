<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateSSADTO;
use PHPUnit\Framework\TestCase;

final class CreateSSADTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_required_fields(): void
    {
        $dto = CreateSSADTO::fromArray([
            'student_id' => 1,
            'primary_service_id' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'minutes_per_session' => 30,
            'frequency' => null,
            'sessions_per_frequency' => null,
            'calculated_minutes' => null,
            'adjusted_minutes' => null,
            'adjustment_notes' => null,
            'tho_minutes' => 10,
            'assigned_therapist_id' => null,
        ]);

        $this->assertSame(1, $dto->studentId);
        $this->assertSame(2, $dto->primaryServiceId);
    }
}
