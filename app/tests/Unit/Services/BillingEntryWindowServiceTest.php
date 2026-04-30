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

    /**
     * A UTC-stored session at 06:00 UTC on Monday Feb 2 == 23:00 PT Sunday Feb 1,
     * which falls in the *prior* week (Mon Jan 26 – Sun Feb 1) for a PT therapist.
     * If we passed PT as the TZ, the week-start must be Jan 26 — not Feb 2.
     */
    public function test_pacific_therapist_session_at_utc_monday_morning_falls_in_prior_local_week(): void
    {
        $sessionUtc = Carbon::parse('2026-02-02 06:00:00', 'UTC');
        $result = $this->service->checkWindow($sessionUtc, null, 'America/Los_Angeles');

        $this->assertSame('2026-01-26', $result->weekStart);
        $this->assertSame('2026-02-01', $result->sessionDate);
    }

    /**
     * Same UTC instant, but interpreted in app TZ (default UTC). Now the session
     * is on Monday Feb 2, which is the start of a NEW week. This documents the
     * behavior change between the old (app-TZ) and new (therapist-TZ) cutoff math.
     */
    public function test_same_utc_instant_in_utc_tz_falls_in_following_week(): void
    {
        $sessionUtc = Carbon::parse('2026-02-02 06:00:00', 'UTC');
        $result = $this->service->checkWindow($sessionUtc); // tz omitted -> defaults to app TZ (UTC in tests)

        $this->assertSame('2026-02-02', $result->weekStart);
        $this->assertSame('2026-02-02', $result->sessionDate);
    }

    /**
     * Cutoff edge: it's Sunday evening PT. A session in the just-past PT week
     * should still be within the window, even though "now" in UTC has rolled
     * past the cutoff (UTC is ~7 hours ahead of PDT).
     */
    public function test_pt_therapist_can_still_submit_late_sunday_when_utc_is_already_past_cutoff(): void
    {
        // Session: Mon Jan 26 PT (start of a week).
        $sessionDate = Carbon::parse('2026-01-26 12:00:00', 'America/Los_Angeles');

        // "Now" — Wed Feb 4 23:30 PT == Thu Feb 5 06:30 UTC. Cutoff (PT) is Wed Feb 4 23:59:59 PT.
        $nowPt = Carbon::parse('2026-02-04 23:30:00', 'America/Los_Angeles');

        $result = $this->service->checkWindow($sessionDate, $nowPt, 'America/Los_Angeles');

        $this->assertTrue($result->isWithinWindow);
        $this->assertSame('2026-02-04 23:59:59', $result->cutoff);
    }

    /**
     * After the PT cutoff: Thursday 00:01 PT, even though it's still "Wednesday
     * something" UTC.
     */
    public function test_pt_therapist_blocked_just_after_local_midnight_thursday(): void
    {
        $sessionDate = Carbon::parse('2026-01-26 12:00:00', 'America/Los_Angeles');
        $nowPt = Carbon::parse('2026-02-05 00:01:00', 'America/Los_Angeles');

        $result = $this->service->checkWindow($sessionDate, $nowPt, 'America/Los_Angeles');

        $this->assertFalse($result->isWithinWindow);
    }
}
