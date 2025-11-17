<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\ChangeTherapistStatusDTO;
use PHPUnit\Framework\TestCase;

final class ChangeTherapistStatusDTOTest extends TestCase
{
    public function test_from_array_creates_dto_with_all_fields(): void
    {
        $data = [
            'status' => 'inactive',
            'reason' => 'Extended leave',
        ];

        $dto = ChangeTherapistStatusDTO::fromArray($data);

        $this->assertSame('inactive', $dto->status);
        $this->assertSame('Extended leave', $dto->reason);
    }

    public function test_from_array_handles_optional_reason(): void
    {
        $data = [
            'status' => 'active',
        ];

        $dto = ChangeTherapistStatusDTO::fromArray($data);

        $this->assertSame('active', $dto->status);
        $this->assertNull($dto->reason);
    }

    public function test_to_array_converts_dto_to_array(): void
    {
        $dto = new ChangeTherapistStatusDTO(
            status: 'active',
            reason: 'Returning from leave'
        );

        $array = $dto->toArray();

        $this->assertSame('active', $array['status']);
        $this->assertSame('Returning from leave', $array['status_reason']);
    }

    public function test_to_array_handles_null_reason(): void
    {
        $dto = new ChangeTherapistStatusDTO(
            status: 'inactive',
            reason: null
        );

        $array = $dto->toArray();

        $this->assertSame('inactive', $array['status']);
        $this->assertNull($array['status_reason']);
    }
}
