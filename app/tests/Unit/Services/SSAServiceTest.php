<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\SSA\Services\SSAService;
use App\Enums\ServiceFrequency;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SSAServiceTest extends TestCase
{
    public function test_calculate_tho_minutes_for_one_time_frequency_ignores_date_range(): void
    {
        $repository = Mockery::mock(SSARepositoryInterface::class);
        $service = new SSAService($repository);

        $thoMinutes = $service->calculateThoMinutes(
            minutesPerSession: 75,
            frequency: ServiceFrequency::ONE_TIME->value,
            sessionsPerFrequency: 8,
            startDate: '2026-01-01',
            endDate: '2026-12-31'
        );

        $this->assertSame(75, $thoMinutes);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
