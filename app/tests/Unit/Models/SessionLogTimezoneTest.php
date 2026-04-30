<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SessionLog;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLogTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_utc_and_end_utc_compose_session_date_with_times_in_utc(): void
    {
        $log = SessionLog::factory()->create([
            'session_date' => '2026-04-30',
            'start_time' => '2026-04-30 16:00:00',
            'end_time' => '2026-04-30 17:00:00',
        ]);

        $startUtc = $log->startUtc();
        $endUtc = $log->endUtc();

        $this->assertSame('UTC', $startUtc->timezoneName);
        $this->assertSame('UTC', $endUtc->timezoneName);
        $this->assertSame('2026-04-30 16:00:00', $startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 17:00:00', $endUtc->format('Y-m-d H:i:s'));
    }

    public function test_end_utc_rolls_to_next_day_when_end_time_numerically_precedes_start(): void
    {
        // 11pm UTC start, 12:30am UTC "end" — the row crosses UTC midnight,
        // so endUtc() must move the date forward by one day.
        $log = SessionLog::factory()->create([
            'session_date' => '2026-04-30',
            'start_time' => '2026-04-30 23:00:00',
            'end_time' => '2026-04-30 00:30:00',
        ]);

        $startUtc = $log->startUtc();
        $endUtc = $log->endUtc();

        $this->assertSame('2026-04-30 23:00:00', $startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-01 00:30:00', $endUtc->format('Y-m-d H:i:s'));
        $this->assertTrue($endUtc->greaterThan($startUtc));
    }

    public function test_local_start_and_local_end_convert_utc_to_supplied_timezone(): void
    {
        $log = SessionLog::factory()->create([
            'session_date' => '2026-04-30',
            'start_time' => '2026-04-30 16:00:00',
            'end_time' => '2026-04-30 17:00:00',
        ]);

        $localStart = $log->localStart('America/Los_Angeles');
        $localEnd = $log->localEnd('America/Los_Angeles');

        $this->assertSame('America/Los_Angeles', $localStart->timezoneName);
        $this->assertSame('America/Los_Angeles', $localEnd->timezoneName);
        // 16:00 UTC = 09:00 PDT on 2026-04-30 (DST in effect)
        $this->assertSame('2026-04-30 09:00:00', $localStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 10:00:00', $localEnd->format('Y-m-d H:i:s'));
    }

    public function test_local_date_is_local_start_at_midnight(): void
    {
        $log = SessionLog::factory()->create([
            'session_date' => '2026-05-01',
            'start_time' => '2026-05-01 06:00:00', // 11pm PT prior day
            'end_time' => '2026-05-01 07:00:00',
        ]);

        $localDate = $log->localDate('America/Los_Angeles');

        // 06:00 UTC on May 1 == 23:00 PT on April 30 (DST). Local date == April 30.
        $this->assertSame('2026-04-30 00:00:00', $localDate->format('Y-m-d H:i:s'));
    }

    public function test_display_timezone_falls_back_through_profile_then_user_then_utc(): void
    {
        // Therapist with TZ on profile only.
        $therapistA = User::factory()->therapist()->create(['timezone' => '']);
        TherapistProfile::factory()->create([
            'user_id' => $therapistA->id,
            'timezone' => 'America/New_York',
        ]);

        $logA = SessionLog::factory()->create(['therapist_id' => $therapistA->id]);
        $logA->setRelation('therapist', $therapistA->fresh(['therapistProfile']));
        $this->assertSame('America/New_York', $logA->displayTimezone());

        // Therapist with no profile TZ but a user-row TZ.
        $therapistB = User::factory()->therapist()->create(['timezone' => 'America/Los_Angeles']);
        TherapistProfile::factory()->create([
            'user_id' => $therapistB->id,
            'timezone' => '',
        ]);

        $logB = SessionLog::factory()->create(['therapist_id' => $therapistB->id]);
        $logB->setRelation('therapist', $therapistB->fresh(['therapistProfile']));
        $this->assertSame('America/Los_Angeles', $logB->displayTimezone());

        // No therapist relation -> UTC.
        $logC = SessionLog::factory()->create();
        $logC->setRelation('therapist', null);
        $this->assertSame('UTC', $logC->displayTimezone());
    }
}
