<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Billing\Services\BillingEntryWindowService;
use App\Exceptions\BillingWindowClosedException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class BillingEntryWindowServiceTest extends TestCase
{
    private BillingEntryWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('billing.entry_window_days_after_week_start', 9);
        $this->service = new BillingEntryWindowService;
    }

    public function test_monday_session_cutoff_is_next_wednesday_end_of_day(): void
    {
        // Session on Monday 2026-01-26
        $sessionDate = Carbon::parse('2026-01-26');
        $result = $this->service->checkWindow($sessionDate);

        // Week start should be Monday 2026-01-26
        $this->assertSame('2026-01-26', $result->weekStart);
        // Cutoff should be Wednesday 2026-02-04 at 23:59:59 (Monday + 9 days, end of day)
        $this->assertSame('2026-02-04 23:59:59', $result->cutoff);
    }

    public function test_friday_session_has_same_cutoff_as_monday(): void
    {
        // Session on Friday 2026-01-30 — same week as Monday 2026-01-26
        $sessionDate = Carbon::parse('2026-01-30');
        $result = $this->service->checkWindow($sessionDate);

        $this->assertSame('2026-01-26', $result->weekStart);
        $this->assertSame('2026-02-04 23:59:59', $result->cutoff);
    }

    public function test_saturday_session_has_same_cutoff_as_weekday(): void
    {
        // Saturday 2026-01-31 is in the Mon 1/26 – Sun 2/1 week
        $sessionDate = Carbon::parse('2026-01-31');
        $result = $this->service->checkWindow($sessionDate);

        $this->assertSame('2026-01-26', $result->weekStart);
        $this->assertSame('2026-02-04 23:59:59', $result->cutoff);
    }

    public function test_sunday_session_has_same_cutoff_as_weekday(): void
    {
        // Sunday 2026-02-01 is in the Mon 1/26 – Sun 2/1 week
        $sessionDate = Carbon::parse('2026-02-01');
        $result = $this->service->checkWindow($sessionDate);

        $this->assertSame('2026-01-26', $result->weekStart);
        $this->assertSame('2026-02-04 23:59:59', $result->cutoff);
    }

    public function test_within_window_when_now_is_before_cutoff(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-03 12:00:00'); // Tuesday before cutoff

        $result = $this->service->checkWindow($sessionDate, $now);

        $this->assertTrue($result->isWithinWindow);
    }

    public function test_outside_window_when_now_is_after_cutoff(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-05 10:00:00'); // Thursday after cutoff

        $result = $this->service->checkWindow($sessionDate, $now);

        $this->assertFalse($result->isWithinWindow);
    }

    public function test_within_window_at_wednesday_9pm(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-04 21:00:00'); // Wednesday 9pm — still within window

        $result = $this->service->checkWindow($sessionDate, $now);

        $this->assertTrue($result->isWithinWindow);
    }

    public function test_within_window_at_wednesday_1159pm(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-04 23:59:59'); // Wednesday 11:59:59pm — still within window

        $result = $this->service->checkWindow($sessionDate, $now);

        $this->assertTrue($result->isWithinWindow);
    }

    public function test_outside_window_at_thursday_midnight(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-05 00:00:00'); // Thursday midnight — outside window

        $result = $this->service->checkWindow($sessionDate, $now);

        $this->assertFalse($result->isWithinWindow);
    }

    public function test_assert_within_window_does_not_throw_when_within(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-03 12:00:00');

        // Should not throw
        $this->service->assertWithinWindow($sessionDate, $now);
        $this->assertTrue(true);
    }

    public function test_assert_within_window_throws_when_outside(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $now = Carbon::parse('2026-02-05 10:00:00');

        $this->expectException(BillingWindowClosedException::class);
        $this->expectExceptionMessage('billing window');

        $this->service->assertWithinWindow($sessionDate, $now);
    }

    public function test_dto_to_array_returns_correct_structure(): void
    {
        $sessionDate = Carbon::parse('2026-01-26');
        $result = $this->service->checkWindow($sessionDate);
        $array = $result->toArray();

        $this->assertArrayHasKey('session_date', $array);
        $this->assertArrayHasKey('week_start', $array);
        $this->assertArrayHasKey('cutoff', $array);
        $this->assertArrayHasKey('is_within_window', $array);
        $this->assertSame('2026-01-26', $array['session_date']);
        $this->assertSame('2026-01-26', $array['week_start']);
        $this->assertIsBool($array['is_within_window']);
    }

    public function test_cutoff_respects_configured_days_after_week_start(): void
    {
        Config::set('billing.entry_window_days_after_week_start', 12);
        $sessionDate = Carbon::parse('2026-01-26');
        $result = $this->service->checkWindow($sessionDate);

        $this->assertSame('2026-01-26', $result->weekStart);
        $this->assertSame('2026-02-07 23:59:59', $result->cutoff);
    }
}
