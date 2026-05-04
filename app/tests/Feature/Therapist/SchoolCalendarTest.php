<?php

declare(strict_types=1);

namespace Tests\Feature\Therapist;

use App\Enums\Role;
use App\Enums\SchoolCalendarEventType;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SchoolCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_schools_where_therapist_has_active_ssa(): void
    {
        $therapist = User::factory()->therapist()->create();

        $assignedSchool = School::factory()->create(['display_name' => 'Assigned School']);
        $assignedStudent = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create([
            'user_id' => $assignedStudent->id,
            'school_id' => $assignedSchool->id,
        ]);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $assignedStudent->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        // Other therapist's school should not appear.
        $otherTherapist = User::factory()->therapist()->create();
        $otherSchool = School::factory()->create(['display_name' => 'Other School']);
        $otherStudent = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create([
            'user_id' => $otherStudent->id,
            'school_id' => $otherSchool->id,
        ]);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $otherStudent->id,
            'assigned_therapist_id' => $otherTherapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.school-calendar.index'));

        $response->assertOk();

        $schools = $response->viewData('schools');
        $schoolIds = $schools->pluck('id')->all();

        $this->assertContains($assignedSchool->id, $schoolIds);
        $this->assertNotContains($otherSchool->id, $schoolIds);
    }

    public function test_index_excludes_schools_from_inactive_ssas(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::COMPLETED->value,
        ]);

        $response = $this->actingAs($therapist)
            ->get(route('therapist.school-calendar.index'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('schools'));
    }

    public function test_events_returns_calendar_events_for_allowed_school(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ]);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);
        $event = SchoolCalendarEvent::factory()->holiday()->create([
            'school_id' => $school->id,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-10',
            'title' => 'Spring Holiday',
        ]);

        $response = $this->actingAs($therapist)
            ->getJson(route('therapist.school-calendar.events', [
                'school' => $school,
                'start' => '2026-03-01',
                'end' => '2026-03-31',
            ]));

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $event->id,
                'title' => 'Spring Holiday',
                'event_type' => SchoolCalendarEventType::HOLIDAY->value,
                'is_holiday' => true,
            ]);
    }

    public function test_events_returns_403_for_school_not_assigned_to_therapist(): void
    {
        $therapist = User::factory()->therapist()->create();

        // Therapist has an active SSA at schoolA but not schoolB.
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $student = User::factory()->create(['role' => Role::STUDENT]);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'school_id' => $schoolA->id,
        ]);
        ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => SSAStatus::ACTIVE->value,
        ]);

        $this->actingAs($therapist)
            ->getJson(route('therapist.school-calendar.events', [
                'school' => $schoolB,
                'start' => '2026-03-01',
                'end' => '2026-03-31',
            ]))
            ->assertForbidden();
    }

    public function test_non_therapist_cannot_access_school_calendar_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();

        $this->actingAs($admin)
            ->get(route('therapist.school-calendar.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('therapist.school-calendar.events', ['school' => $school]))
            ->assertForbidden();
    }
}
