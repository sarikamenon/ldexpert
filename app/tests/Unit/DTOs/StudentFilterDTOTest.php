<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\StudentFilterDTO;
use PHPUnit\Framework\TestCase;

final class StudentFilterDTOTest extends TestCase
{
    public function test_from_request_uses_defaults(): void
    {
        $dto = StudentFilterDTO::fromRequest([]);

        $this->assertNull($dto->search);
        $this->assertNull($dto->status);
        $this->assertSame(15, $dto->perPage);
    }

    public function test_from_request_assigns_values(): void
    {
        $dto = StudentFilterDTO::fromRequest([
            'search' => 'Ava',
            'status' => 'inactive',
            'per_page' => 50,
        ]);

        $this->assertSame('Ava', $dto->search);
        $this->assertSame('inactive', $dto->status);
        $this->assertSame(50, $dto->perPage);
    }

    public function test_to_array_serializes_properties(): void
    {
        $dto = new StudentFilterDTO(
            search: 'Mia',
            status: 'active',
            perPage: 25
        );

        $array = $dto->toArray();

        $this->assertSame('Mia', $array['search']);
        $this->assertSame('active', $array['status']);
        $this->assertSame(25, $array['perPage']);
    }
}
