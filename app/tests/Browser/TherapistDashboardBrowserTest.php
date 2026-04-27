<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class TherapistDashboardBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_therapist_can_expand_today_schedule_list(): void
    {
        $therapist = User::factory()->therapist()->create();
        $service = Service::factory()->create(['name' => 'Speech Therapy']);

        foreach (range(1, 8) as $index) {
            $student = User::factory()->student()->create([
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

        $this->browse(function (Browser $browser) use ($therapist): void {
            $browser->loginAs($therapist)
                ->visit('/therapist/dashboard')
                ->assertSee("Today's Schedule")
                ->assertSee('8 schedules today')
                ->assertSee('View full calendar')
                ->assertSee('Dashboard Student 6')
                ->assertDontSee('Dashboard Student 8')
                ->press('Show 2 more schedules')
                ->assertSee('Dashboard Student 8')
                ->assertSee('Show less')
                ->clickLink('View full calendar')
                ->assertPathIs('/therapist/schedule-calendar');
        });
    }
}
