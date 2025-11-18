<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\ChangeStudentStatusDTO;
use PHPUnit\Framework\TestCase;

final class ChangeStudentStatusDTOTest extends TestCase
{
    public function test_from_array_creates_dto(): void
    {
        $dto = ChangeStudentStatusDTO::fromArray([
            'status' => 'inactive',
            'reason' => 'Graduated',
        ]);

        $this->assertSame('inactive', $dto->status);
        $this->assertSame('Graduated', $dto->reason);
    }

    public function test_from_array_handles_missing_reason(): void
    {
        $dto = ChangeStudentStatusDTO::fromArray([
            'status' => 'active',
        ]);

        $this->assertSame('active', $dto->status);
        $this->assertNull($dto->reason);
    }

    public function test_to_array_returns_expected_payload(): void
    {
        $dto = new ChangeStudentStatusDTO('inactive', 'Moved schools');

        $array = $dto->toArray();

        $this->assertSame('inactive', $array['status']);
        $this->assertArrayNotHasKey('reason', $array);
    }
}
