<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\RateType;
use PHPUnit\Framework\TestCase;

final class SessionLogRateServiceTest extends TestCase
{
    public function test_calculate_billable_amount_hourly(): void
    {
        $service = new SessionLogRateService;
        $amount = $service->calculateBillableAmount(RateType::HOURLY, 120.0, 90);

        $this->assertSame(180.0, $amount);
    }

    public function test_calculate_billable_amount_flat(): void
    {
        $service = new SessionLogRateService;
        $amount = $service->calculateBillableAmount(RateType::FLAT, 150.0, 45);

        $this->assertSame(150.0, $amount);
    }
}
