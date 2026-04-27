<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_session_time_submitted_this_week_for_submitted_and_approved_logs(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $weekStart = now()->startOfWeek();

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'session_date' => $weekStart->copy()->addDay()->toDateString(),
            'duration_minutes' => 45,
            'status' => SessionLogStatus::SUBMITTED,
            'submitted_at' => $weekStart->copy()->addDay()->setTime(10, 0),
            'submitted_by_id' => $therapist->id,
        ]);

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'session_date' => $weekStart->copy()->addDays(2)->toDateString(),
            'duration_minutes' => 60,
            'status' => SessionLogStatus::APPROVED,
            'submitted_at' => $weekStart->copy()->addDays(2)->setTime(11, 0),
            'submitted_by_id' => $therapist->id,
            'approved_at' => $weekStart->copy()->addDays(3)->setTime(9, 0),
            'approved_by_id' => User::factory()->admin()->create()->id,
        ]);

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'session_date' => $weekStart->copy()->addDays(3)->toDateString(),
            'duration_minutes' => 30,
            'status' => SessionLogStatus::DRAFT,
        ]);

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $student->id,
            'ssa_id' => $ssa->id,
            'service_id' => $service->id,
            'session_date' => $weekStart->copy()->subWeek()->addDay()->toDateString(),
            'duration_minutes' => 90,
            'status' => SessionLogStatus::APPROVED,
            'submitted_at' => $weekStart->copy()->subWeek()->addDay()->setTime(10, 0),
            'submitted_by_id' => $therapist->id,
            'approved_at' => $weekStart->copy()->subWeek()->addDays(2)->setTime(9, 0),
            'approved_by_id' => User::factory()->admin()->create()->id,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Session Time Submitted This Week');
        $response->assertSeeText('1h 45m');
        $response->assertSeeText('2 submitted');
    }

    public function test_dashboard_shows_today_schedule_summary_and_full_calendar_link(): void
    {
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);
        $service = Service::factory()->create(['status' => ServiceStatus::ACTIVE]);

        foreach (range(1, 8) as $index) {
            $student = User::factory()->create([
                'role' => Role::STUDENT,
                'name' => "Dashboard Student {$index}",
            ]);
            $ssa = ServiceSupportAgreement::factory()->create([
                'student_id' => $student->id,
                'primary_service_id' => $service->id,
                'assigned_therapist_id' => $therapist->id,
                'status' => SSAStatus::ACTIVE,
            ]);

            Schedule::factory()->create([
                'therapist_id' => $therapist->id,
                'student_id' => $student->id,
                'ssa_id' => $ssa->id,
                'service_id' => $service->id,
                'schedule_date' => now()->toDateString(),
                'start_time' => now()->startOfDay()->addHours($index)->format('H:i'),
                'end_time' => now()->startOfDay()->addHours($index)->addMinutes(45)->format('H:i'),
            ]);
        }

        $response = $this->actingAs($therapist)
            ->get(route('therapist.dashboard'));

        $response->assertOk();
        $response->assertSeeText('8 schedules today');
        $response->assertSeeText('View full calendar');
        $response->assertSeeText('Show 2 more schedules');
        $response->assertSee(route('therapist.schedule-calendar.index'), false);
    }
}
