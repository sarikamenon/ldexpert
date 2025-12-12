<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogRateService;
use App\Domain\Therapist\Services\SessionLogService;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class SessionLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_updates_status_to_submitted(): void
    {
        $therapist = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist->id,
        ]);

        $repository = Mockery::mock(SessionLogRepositoryInterface::class);
        $repository->shouldReceive('submit')
            ->once()
            ->with($sessionLog, $therapist)
            ->andReturn($sessionLog->fresh());

        $rateService = Mockery::mock(SessionLogRateService::class);
        $service = new SessionLogService($repository, $rateService);

        $result = $service->submit($therapist, $sessionLog);

        $this->assertInstanceOf(SessionLog::class, $result);
    }

    public function test_submit_throws_exception_for_non_owner(): void
    {
        $therapist1 = User::factory()->therapist()->create();
        $therapist2 = User::factory()->therapist()->create();
        $sessionLog = SessionLog::factory()->draft()->create([
            'therapist_id' => $therapist1->id,
        ]);

        $repository = Mockery::mock(SessionLogRepositoryInterface::class);
        $rateService = Mockery::mock(SessionLogRateService::class);
        $service = new SessionLogService($repository, $rateService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Therapist does not have access to this session log.');

        $service->submit($therapist2, $sessionLog);
    }

    public function test_finalize_updates_status_to_finalized(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->submitted()->create();

        $repository = Mockery::mock(SessionLogRepositoryInterface::class);
        $repository->shouldReceive('finalize')
            ->once()
            ->with($sessionLog, $admin)
            ->andReturn($sessionLog->fresh());

        $rateService = Mockery::mock(SessionLogRateService::class);
        $service = new SessionLogService($repository, $rateService);

        $result = $service->finalize($admin, $sessionLog);

        $this->assertInstanceOf(SessionLog::class, $result);
    }

    public function test_finalize_throws_exception_for_non_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $sessionLog = SessionLog::factory()->draft()->create();

        $repository = Mockery::mock(SessionLogRepositoryInterface::class);
        $rateService = Mockery::mock(SessionLogRateService::class);
        $service = new SessionLogService($repository, $rateService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session log must be submitted before finalization.');

        $service->finalize($admin, $sessionLog);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
