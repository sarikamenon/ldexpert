<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\User;
use Tests\TestCase;

final class SessionLogTabsTest extends TestCase
{
    public function test_therapist_can_view_session_logs_tab_on_ssa_detail_page(): void
    {
        $therapist = User::factory()->therapist()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        SessionLog::factory()->create([
            'ssa_id' => $ssa->id,
            'therapist_id' => $therapist->id,
            'session_date' => now()->startOfMonth(),
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewIs('therapist.ssas.show');
        $response->assertViewHas('datatableUrl');
        $response->assertViewHas('ssaId', $ssa->id);
        $response->assertSee('Session Logs');
    }

    public function test_therapist_can_view_session_logs_tab_on_student_detail_page(): void
    {
        $therapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        SessionLog::factory()->create([
            'student_id' => $student->id,
            'therapist_id' => $therapist->id,
            'session_date' => now()->startOfMonth(),
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.students.show', ['student' => $student, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewIs('therapist.students.show');
        $response->assertViewHas('datatableUrl');
        $response->assertViewHas('studentId', $student->id);
        $response->assertSee('Session Logs');
    }

    public function test_therapist_cannot_view_other_therapist_session_logs(): void
    {
        $therapist = User::factory()->therapist()->create();
        $otherTherapist = User::factory()->therapist()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']));

        $response->assertOk();
        $response->assertViewHas('sessionLogStatuses');
        $response->assertViewHas('datatableUrl');
    }

    public function test_therapist_cannot_access_unassigned_ssa_session_logs(): void
    {
        $therapist = User::factory()->therapist()->create();
        $otherTherapist = User::factory()->therapist()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $otherTherapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $this->actingAs($therapist)
            ->get(route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']))
            ->assertNotFound();
    }

    public function test_therapist_cannot_access_unassigned_student_session_logs(): void
    {
        $therapist = User::factory()->therapist()->create();
        $otherTherapist = User::factory()->therapist()->create();
        $student = User::factory()->student()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $otherTherapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $this->actingAs($therapist)
            ->get(route('therapist.students.show', ['student' => $student, 'tab' => 'session_logs']))
            ->assertForbidden();
    }

    public function test_session_logs_tab_filters_work_correctly(): void
    {
        $therapist = User::factory()->therapist()->create();

        /** @var ServiceSupportAgreement $ssa */
        $ssa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.ssas.show', [
                'ssa' => $ssa,
                'tab' => 'session_logs',
                'status' => 'submitted',
            ]));

        $response->assertOk();
        $response->assertViewHas('sessionLogStatuses');
        $response->assertViewHas('sessionLogFilters');
        $response->assertViewHas('datatableUrl');
    }
}
