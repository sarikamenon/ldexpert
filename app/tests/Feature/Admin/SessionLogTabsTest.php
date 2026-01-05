<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Tests\TestCase;

final class SessionLogTabsTest extends TestCase
{
    public function test_admin_can_view_session_logs_tab_on_student_detail_page(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $student = User::factory()->student()->create();

        SessionLog::factory()->create([
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.students.show', ['student' => $student, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewIs('admin.students.show');
        $response->assertViewHas('sessionLogs');
        $response->assertSee('Session Logs');
    }

    public function test_admin_can_view_session_logs_tab_on_therapist_detail_page(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewIs('admin.therapists.show');
        $response->assertViewHas('sessionLogs');
        $response->assertSee('Session Logs');
    }

    public function test_admin_can_view_session_logs_tab_on_ssa_detail_page(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->active()->create();

        SessionLog::factory()->create([
            'ssa_id' => $ssa->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewIs('admin.ssas.show');
        $response->assertViewHas('sessionLogs');
        $response->assertSee('Session Logs');
    }

    public function test_session_logs_tab_filters_by_student(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $studentLog = SessionLog::factory()->create([
            'student_id' => $student->id,
        ]);

        SessionLog::factory()->create([
            'student_id' => $otherStudent->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.students.show', ['student' => $student, 'tab' => 'session_logs']));

        $response->assertOk();
        $sessionLogs = $response->viewData('sessionLogs');
        $this->assertCount(1, $sessionLogs);
        $this->assertEquals($studentLog->id, $sessionLogs->first()->id);
    }

    public function test_session_logs_tab_filters_by_therapist(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create();
        $otherTherapist = User::factory()->therapist()->create();

        $therapistLog = SessionLog::factory()->create([
            'therapist_id' => $therapist->id,
        ]);

        SessionLog::factory()->create([
            'therapist_id' => $otherTherapist->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'session_logs']));

        $response->assertOk();
        $sessionLogs = $response->viewData('sessionLogs');
        $this->assertCount(1, $sessionLogs);
        $this->assertEquals($therapistLog->id, $sessionLogs->first()->id);
    }

    public function test_session_logs_tab_filters_by_ssa(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->active()->create();

        /** @var ServiceSupportAgreement $otherSsa */
        $otherSsa = ServiceSupportAgreement::factory()->active()->create();

        $ssaLog = SessionLog::factory()->create([
            'ssa_id' => $ssa->id,
        ]);

        SessionLog::factory()->create([
            'ssa_id' => $otherSsa->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']));

        $response->assertOk();
        $sessionLogs = $response->viewData('sessionLogs');
        $this->assertCount(1, $sessionLogs);
        $this->assertEquals($ssaLog->id, $sessionLogs->first()->id);
    }
}
