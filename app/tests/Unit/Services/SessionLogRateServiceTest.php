<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Enums\RateType;
use App\Enums\SessionOutcome;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SessionLogRateServiceTest extends TestCase
{
    public function test_calculate_billable_amount_hourly(): void
    {
        $service = $this->createServiceWithMocks();
        $amount = $service->calculateBillableAmount(RateType::HOURLY, 120.0, 90);

        $this->assertSame(180.0, $amount);
    }

    public function test_calculate_billable_amount_flat(): void
    {
        $service = $this->createServiceWithMocks();
        $amount = $service->calculateBillableAmount(RateType::FLAT, 150.0, 45);

        $this->assertSame(150.0, $amount);
    }

    public function test_calculate_dual_billing_private_student_always_uses_regular_rate(): void
    {
        $therapistProfile = new TherapistProfile;
        $therapistProfile->id = 1;
        $school = Mockery::mock(School::class)->makePartial();
        $school->is_private_student = true;
        $therapistRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 100.0,
            'no_show_rate' => 25.0,
            'no_show_rate_type' => RateType::FLAT,
        ];
        $schoolRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 150.0,
            'no_show_rate' => 30.0,
            'no_show_rate_type' => RateType::FLAT,
        ];

        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistRepository->shouldReceive('findProfileByUserId')->with(1)->andReturn($therapistProfile);

        $therapistContract = Mockery::mock(TherapistContract::class)->makePartial();
        $therapistContract->id = 10;
        $schoolContract = Mockery::mock(SchoolContract::class)->makePartial();
        $schoolContract->id = 20;

        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $therapistContractRepository->shouldReceive('findActiveContractForDate')->andReturn($therapistContract);
        $therapistContractRepository->shouldReceive('getServiceRate')->with(10, 5)->andReturn($therapistRate);

        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $schoolContractRepository->shouldReceive('findActiveContractForDate')->andReturn($schoolContract);
        $schoolContractRepository->shouldReceive('getServiceRate')->with(20, 5)->andReturn($schoolRate);

        $schoolRepository = Mockery::mock(SchoolRepositoryInterface::class);
        $schoolRepository->shouldReceive('find')->with(100)->andReturn($school);

        $service = new SessionLogRateService(
            $therapistRepository,
            $therapistContractRepository,
            $schoolContractRepository,
            $schoolRepository
        );

        $billingNoShow = $service->calculateDualBilling(1, 100, 5, '2025-01-15', 60, SessionOutcome::NO_SHOW);
        $billingAdministered = $service->calculateDualBilling(1, 100, 5, '2025-01-15', 60, SessionOutcome::SERVICES_ADMINISTERED);

        $this->assertSame(100.0, $billingNoShow['therapist']['billable_amount']);
        $this->assertSame(150.0, $billingNoShow['school']['invoice_amount']);
        $this->assertSame($billingAdministered['therapist']['billable_amount'], $billingNoShow['therapist']['billable_amount']);
        $this->assertSame($billingAdministered['school']['invoice_amount'], $billingNoShow['school']['invoice_amount']);
    }

    public function test_calculate_dual_billing_school_student_uses_no_show_rate_when_outcome_no_show(): void
    {
        $therapistProfile = new TherapistProfile;
        $therapistProfile->id = 1;
        $school = Mockery::mock(School::class)->makePartial();
        $school->is_private_student = false;
        $therapistRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 100.0,
            'no_show_rate' => 25.0,
            'no_show_rate_type' => RateType::FLAT,
        ];
        $schoolRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 150.0,
            'no_show_rate' => 30.0,
            'no_show_rate_type' => RateType::FLAT,
        ];

        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistRepository->shouldReceive('findProfileByUserId')->with(1)->andReturn($therapistProfile);

        $therapistContract = Mockery::mock(TherapistContract::class)->makePartial();
        $therapistContract->id = 10;
        $schoolContract = Mockery::mock(SchoolContract::class)->makePartial();
        $schoolContract->id = 20;

        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $therapistContractRepository->shouldReceive('findActiveContractForDate')->andReturn($therapistContract);
        $therapistContractRepository->shouldReceive('getServiceRate')->with(10, 5)->andReturn($therapistRate);

        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $schoolContractRepository->shouldReceive('findActiveContractForDate')->andReturn($schoolContract);
        $schoolContractRepository->shouldReceive('getServiceRate')->with(20, 5)->andReturn($schoolRate);

        $schoolRepository = Mockery::mock(SchoolRepositoryInterface::class);
        $schoolRepository->shouldReceive('find')->with(100)->andReturn($school);

        $service = new SessionLogRateService(
            $therapistRepository,
            $therapistContractRepository,
            $schoolContractRepository,
            $schoolRepository
        );

        $billingNoShow = $service->calculateDualBilling(1, 100, 5, '2025-01-15', 60, SessionOutcome::NO_SHOW);
        $billingAdministered = $service->calculateDualBilling(1, 100, 5, '2025-01-15', 60, SessionOutcome::SERVICES_ADMINISTERED);

        $this->assertSame(25.0, $billingNoShow['therapist']['billable_amount']);
        $this->assertSame(30.0, $billingNoShow['school']['invoice_amount']);
        $this->assertSame(100.0, $billingAdministered['therapist']['billable_amount']);
        $this->assertSame(150.0, $billingAdministered['school']['invoice_amount']);
    }

    public function test_calculate_dual_billing_non_billable_outcome_sets_zero_amounts(): void
    {
        $therapistProfile = new TherapistProfile;
        $therapistProfile->id = 1;
        $school = Mockery::mock(School::class)->makePartial();
        $school->is_private_student = false;
        $therapistRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 100.0,
            'no_show_rate' => 25.0,
            'no_show_rate_type' => RateType::FLAT,
        ];
        $schoolRate = [
            'rate_type' => RateType::HOURLY,
            'rate_amount' => 150.0,
            'no_show_rate' => 30.0,
            'no_show_rate_type' => RateType::FLAT,
        ];

        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistRepository->shouldReceive('findProfileByUserId')->with(1)->andReturn($therapistProfile);

        $therapistContract = Mockery::mock(TherapistContract::class)->makePartial();
        $therapistContract->id = 10;
        $schoolContract = Mockery::mock(SchoolContract::class)->makePartial();
        $schoolContract->id = 20;

        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $therapistContractRepository->shouldReceive('findActiveContractForDate')->andReturn($therapistContract);
        $therapistContractRepository->shouldReceive('getServiceRate')->with(10, 5)->andReturn($therapistRate);

        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $schoolContractRepository->shouldReceive('findActiveContractForDate')->andReturn($schoolContract);
        $schoolContractRepository->shouldReceive('getServiceRate')->with(20, 5)->andReturn($schoolRate);

        $schoolRepository = Mockery::mock(SchoolRepositoryInterface::class);
        $schoolRepository->shouldReceive('find')->with(100)->andReturn($school);

        $service = new SessionLogRateService(
            $therapistRepository,
            $therapistContractRepository,
            $schoolContractRepository,
            $schoolRepository
        );

        $billing = $service->calculateDualBilling(1, 100, 5, '2025-01-15', 60, SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT);

        $this->assertSame(0.0, $billing['therapist']['billable_amount']);
        $this->assertSame(0.0, $billing['school']['invoice_amount']);
    }

    protected function createServiceWithMocks(): SessionLogRateService
    {
        $therapistRepository = Mockery::mock(TherapistRepositoryInterface::class);
        $therapistContractRepository = Mockery::mock(TherapistContractRepositoryInterface::class);
        $schoolContractRepository = Mockery::mock(SchoolContractRepositoryInterface::class);
        $schoolRepository = Mockery::mock(SchoolRepositoryInterface::class);

        return new SessionLogRateService(
            $therapistRepository,
            $therapistContractRepository,
            $schoolContractRepository,
            $schoolRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
