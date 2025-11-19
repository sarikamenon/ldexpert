<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\TherapistFilterDTO;
use PHPUnit\Framework\TestCase;

final class TherapistFilterDTOTest extends TestCase
{
    public function test_from_request_creates_dto_with_all_fields(): void
    {
        $data = [
            'search' => 'john',
            'status' => 'active',
        ];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertSame('john', $dto->search);
        $this->assertSame('active', $dto->status);
    }

    public function test_from_request_handles_optional_fields(): void
    {
        $data = [];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertNull($dto->search);
        $this->assertNull($dto->status);
    }

    public function test_from_request_handles_partial_data(): void
    {
        $data = [
            'search' => 'jane',
        ];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertSame('jane', $dto->search);
    }

    public function test_to_array_converts_dto_to_array(): void
    {
        $dto = new TherapistFilterDTO(
            search: 'therapist',
            status: 'inactive',
        );

        $array = $dto->toArray();

        $this->assertSame('therapist', $array['search']);
        $this->assertSame('inactive', $array['status']);
    }

    public function test_to_array_handles_null_values(): void
    {
        $dto = new TherapistFilterDTO(
            search: null,
            status: null,
        );

        $array = $dto->toArray();

        $this->assertNull($array['search']);
        $this->assertNull($array['status']);
    }
}
