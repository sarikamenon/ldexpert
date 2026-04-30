<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleTimezoneFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_filter_uses_therapist_local_day_and_orders_by_local_start(): void
    {
        // Therapist in America/New_York (UTC-4 during DST).
        $therapist = User::factory()->create([
            'role' => Role::THERAPIST,
            'timezone' => 'America/New_York',
        ]);

        // Yesterday 20:00 ET = today 00:00 UTC. Should NOT appear in today's list.
        $yesterdayLate = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => '2026-04-30',
            'start_time' => '00:00:00',
            'end_time' => '01:00:00',
        ]);

        // Today 07:00 ET = today 11:00 UTC. Should appear, and come first.
        $morning = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => '2026-04-30',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        // Today 20:00 ET = tomorrow 00:00 UTC. Should appear, and come last.
        $evening = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'schedule_date' => '2026-05-01',
            'start_time' => '00:00:00',
            'end_time' => '01:00:00',
        ]);

        $repository = app(ScheduleRepositoryInterface::class);
        $filters = new ScheduleFilterDTO(date: '2026-04-30');

        $result = $repository->getSchedulesForTherapist($therapist, $filters);

        $ids = $result->pluck('id')->all();

        $this->assertNotContains($yesterdayLate->id, $ids, 'Yesterday 20:00 ET (today 00:00 UTC) should not be in today');
        $this->assertSame([$morning->id, $evening->id], $ids, 'Should be ordered by local start time');
    }
}
