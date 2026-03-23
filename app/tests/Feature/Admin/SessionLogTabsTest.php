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
        $response->assertViewHas('datatableUrl');
        $response->assertViewHas('studentId', $student->id);
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
        $response->assertViewHas('datatableUrl');
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
        $response->assertViewHas('datatableUrl');
        $response->assertSee('Session Logs');
    }

    public function test_session_logs_tab_has_correct_filters_for_student(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $student = User::factory()->student()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.students.show', ['student' => $student, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewHas('sessionLogStatuses');
        $response->assertViewHas('sessionLogFilters');
        $response->assertViewHas('studentId', $student->id);
    }

    public function test_session_logs_tab_has_correct_filters_for_therapist(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewHas('sessionLogStatuses');
        $response->assertViewHas('sessionLogFilters');
    }

    public function test_session_logs_tab_has_correct_filters_for_ssa(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->active()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewHas('sessionLogStatuses');
        $response->assertViewHas('sessionLogFilters');
    }
}
