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
            'per_page' => 50,
        ];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertSame('john', $dto->search);
        $this->assertSame('active', $dto->status);
        $this->assertSame(50, $dto->perPage);
    }

    public function test_from_request_handles_optional_fields(): void
    {
        $data = [];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertNull($dto->search);
        $this->assertNull($dto->status);
        $this->assertSame(15, $dto->perPage);
    }

    public function test_from_request_uses_defaults_for_per_page(): void
    {
        $data = [
            'search' => 'jane',
        ];

        $dto = TherapistFilterDTO::fromRequest($data);

        $this->assertSame('jane', $dto->search);
        $this->assertSame(15, $dto->perPage);
    }

    public function test_to_array_converts_dto_to_array(): void
    {
        $dto = new TherapistFilterDTO(
            search: 'therapist',
            status: 'inactive',
            perPage: 100
        );

        $array = $dto->toArray();

        $this->assertSame('therapist', $array['search']);
        $this->assertSame('inactive', $array['status']);
        $this->assertSame(100, $array['per_page']);
    }

    public function test_to_array_handles_null_values(): void
    {
        $dto = new TherapistFilterDTO(
            search: null,
            status: null,
            perPage: 25
        );

        $array = $dto->toArray();

        $this->assertNull($array['search']);
        $this->assertNull($array['status']);
        $this->assertSame(25, $array['per_page']);
    }
}
