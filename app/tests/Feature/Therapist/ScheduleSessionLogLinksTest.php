<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\BillingStatus;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleSessionLogLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_pending_schedule_includes_bill_url_for_session_log_creation(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => Carbon::now()->copy()->subDay()->toDateString(),
            'billing_status' => BillingStatus::PENDING,
        ]);

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.schedule.schedules', [
                'date' => $schedule->schedule_date->format('Y-m-d'),
            ]));

        $response->assertOk();

        $payload = $response->json('schedules');
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);

        $scheduleData = $payload[0];
        $this->assertSame('pending', $scheduleData['billing_status']);
        $this->assertTrue((bool) $scheduleData['is_past']);
        $this->assertFalse((bool) $scheduleData['is_billed']);
        $this->assertSame(
            route('therapist.session-logs.create.from-schedule', $schedule),
            $scheduleData['bill_url']
        );

        Carbon::setTestNow();
    }

    public function test_past_billed_schedule_includes_session_log_url(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $therapist = User::factory()->therapist()->create();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => Carbon::now()->copy()->subDay()->toDateString(),
            'billing_status' => BillingStatus::BILLED,
        ]);

        /** @var SessionLog $sessionLog */
        $sessionLog = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_id' => $schedule->id,
            'session_date' => $schedule->schedule_date,
        ]);

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.schedule.schedules', [
                'date' => $schedule->schedule_date->format('Y-m-d'),
            ]));

        $response->assertOk();

        $payload = $response->json('schedules');
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);

        $scheduleData = $payload[0];
        $this->assertSame('billed', $scheduleData['billing_status']);
        $this->assertTrue((bool) $scheduleData['is_past']);
        $this->assertTrue((bool) $scheduleData['is_billed']);
        $this->assertSame(
            route('therapist.session-logs.show', $sessionLog),
            $scheduleData['session_log_url']
        );

        Carbon::setTestNow();
    }
}
