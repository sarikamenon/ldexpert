<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\CreateSessionLogDTO;
use App\Enums\RateType;
use PHPUnit\Framework\TestCase;

final class CreateSessionLogDTOTest extends TestCase
{
    public function test_from_array_sets_defaults_and_to_array_includes_status(): void
    {
        $dto = CreateSessionLogDTO::fromArray([
            'therapist_id' => 1,
            'student_id' => 2,
            'ssa_id' => 3,
            'service_id' => 4,
            'schedule_id' => null,
            'school_id' => 5,
            'session_date' => '2025-12-12',
            'start_time' => '2025-12-12 10:00:00',
            'end_time' => '2025-12-12 11:00:00',
            'duration_minutes' => 60,
            'tho_minutes' => 30,
            'notes' => 'Sample session notes with sufficient length lorem ipsum dolor sit amet.',
            'is_billable_therapist' => true,
            'therapist_contract_id' => 9,
            'therapist_rate_type' => RateType::HOURLY,
            'therapist_rate_amount' => 100.0,
            'therapist_billable_amount' => 100.0,
            'is_billable_school' => true,
            'school_contract_id' => 10,
            'school_rate_type' => RateType::FLAT,
            'school_rate_amount' => 200.0,
            'school_invoice_amount' => 200.0,
            'is_rate_override' => false,
            'override_reason' => null,
            'cancellation_reason' => null,
        ]);

        $array = $dto->toArray();

        $this->assertSame('draft', $array['status']);
        $this->assertSame('virtual', $array['delivery_mode']);
        $this->assertFalse($array['is_group']);
        $this->assertSame(1, $array['therapist_id']);
        $this->assertSame(2, $array['student_id']);
        $this->assertSame(3, $array['ssa_id']);
        $this->assertSame(4, $array['service_id']);
        $this->assertSame(60, $array['duration_minutes']);
        $this->assertSame(30, $array['tho_minutes']);
        $this->assertSame('H', $array['therapist_rate_type']);
        $this->assertSame(100.0, $array['therapist_rate_amount']);
        $this->assertSame(200.0, $array['school_invoice_amount']);
    }
}
