<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Time;

use App\Domain\Time\UserTimezoneService;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

final class UserTimezoneServiceTest extends TestCase
{
    private UserTimezoneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(UserTimezoneService::class);
    }

    public function test_parse_user_local_to_utc_uses_user_timezone(): void
    {
        $user = new User;
        $user->timezone = 'America/New_York';

        $localString = '2025-01-15 10:00:00';

        $utc = $this->service->parseUserLocalToUtc($localString, $user);

        $this->assertSame('2025-01-15 15:00:00', $utc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $utc->timezoneName);
    }

    public function test_to_user_timezone_converts_from_utc(): void
    {
        $user = new User;
        $user->timezone = 'America/Los_Angeles';

        $utc = Carbon::create(2025, 1, 15, 18, 0, 0, 'UTC');

        $local = $this->service->toUserTimezone($utc, $user);

        $this->assertSame('2025-01-15 10:00:00', $local->format('Y-m-d H:i:s'));
        $this->assertSame('America/Los_Angeles', $local->timezoneName);
    }

    public function test_user_day_utc_range_for_user_timezone(): void
    {
        $user = new User;
        $user->timezone = 'America/New_York';

        [$startUtc, $endUtc] = $this->service->userDayUtcRange('2025-03-10', $user);

        // On this date America/New_York is in DST (UTC-4)
        $this->assertSame('2025-03-10 04:00:00', $startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2025-03-11 03:59:59', $endUtc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $startUtc->timezoneName);
        $this->assertSame('UTC', $endUtc->timezoneName);
    }
}
