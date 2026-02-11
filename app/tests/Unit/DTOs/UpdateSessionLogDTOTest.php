<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\UpdateSessionLogDTO;
use App\Enums\RateType;
use PHPUnit\Framework\TestCase;

final class UpdateSessionLogDTOTest extends TestCase
{
    public function test_to_array_contains_only_set_fields(): void
    {
        $dto = UpdateSessionLogDTO::fromArray([
            'student_id' => 2,
            'therapist_rate_type' => RateType::HOURLY,
            'therapist_rate_amount' => 120.5,
            'therapist_billable_amount' => 130.5,
            'is_rate_override' => true,
            'override_reason' => 'Admin override for reconciliation',
        ]);

        $array = $dto->toArray();

        $this->assertArrayHasKey('student_id', $array);
        $this->assertSame(2, $array['student_id']);
        $this->assertSame('H', $array['therapist_rate_type']);
        $this->assertSame(120.5, $array['therapist_rate_amount']);
        $this->assertSame(130.5, $array['therapist_billable_amount']);
        $this->assertTrue($array['is_rate_override']);
        $this->assertSame('Admin override for reconciliation', $array['override_reason']);
        $this->assertArrayNotHasKey('school_rate_amount', $array);
    }
}
