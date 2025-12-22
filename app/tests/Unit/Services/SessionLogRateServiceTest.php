<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\RateType;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SessionLogRateServiceTest extends TestCase
{
    public function test_calculate_billable_amount_hourly(): void
    {
        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $service = new SessionLogRateService($therapistRepository, $therapistContractRepository, $schoolContractRepository);
        $amount = $service->calculateBillableAmount(RateType::HOURLY, 120.0, 90);

        $this->assertSame(180.0, $amount);
    }

    public function test_calculate_billable_amount_flat(): void
    {
        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $service = new SessionLogRateService($therapistRepository, $therapistContractRepository, $schoolContractRepository);
        $amount = $service->calculateBillableAmount(RateType::FLAT, 150.0, 45);

        $this->assertSame(150.0, $amount);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
