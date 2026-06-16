<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\WelcomeStudentMail;
use App\Models\Position;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private School $school;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->school = School::factory()->create();
        $this->student = User::factory()
            ->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::ACTIVE->value,
            ]);

        StudentProfile::factory()->create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'first_name' => 'Existing',
            'last_name' => 'Student',
        ]);
    }

    public function test_admin_can_view_students_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.index'));

        $response->assertOk()
            ->assertViewIs('admin.students.index')
            ->assertViewHas('students')
            ->assertViewHas('metrics')
            ->assertViewHas('filters')
            ->assertViewHas('statuses')
            ->assertViewHas('schools')
            ->assertViewHas('datatableUrl');
    }

    public function test_students_data_returns_datatables_json(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.students.data'), [
            '_token' => csrf_token(),
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'filter_search' => '',
            'filter_status' => '',
            'filter_school_id' => '',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        0, 1, 2, 3, 4, 5, 6, 7, 8,
                    ],
                ],
            ]);
        $this->assertSame(1, $response->json('draw'));
        $this->assertGreaterThanOrEqual(0, $response->json('recordsTotal'));
        $this->assertGreaterThanOrEqual(0, $response->json('recordsFiltered'));
    }

    public function test_students_data_requires_admin(): void
    {
        $response = $this->actingAs($this->student)->postJson(route('admin.students.data'), [
            '_token' => csrf_token(),
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_view_students_index(): void
    {
        $response = $this->actingAs(User::factory()->student()->create())->get(route('admin.students.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.create'));

        $response->assertOk()
            ->assertViewIs('admin.students.create')
            ->assertViewHas('states')
            ->assertViewHas('timezones')
            ->assertViewHas('schools')
            ->assertViewHas('statuses')
            ->assertViewHas('genderOptions')
            ->assertViewHas('isEdit', false)
            ->assertViewHas('profile', null)
            ->assertViewHas('preselectedSchoolId', null)
            ->assertViewHas('preselectedTimezone', null)
            ->assertViewHas('privateStudentIdsJson')
            ->assertViewHas('privateFamilyContactsJson');
    }

    public function test_create_form_exposes_private_family_contacts_payload(): void
    {
        $privateFamily = School::factory()->create([
            'is_private_student' => true,
            'contact_first_name' => "O'Brien",
            'contact_last_name' => 'Family "Trust"',
            'contact_email' => 'obrien@example.com',
            'contact_phone' => '555-123-4567',
            'timezone' => 'America/Los_Angeles',
        ]);

        // A non-private school must NOT appear in the contacts payload, but its timezone
        // MUST appear in the school-timezones map so selecting it fills the timezone field.
        $regularSchool = School::factory()->create([
            'is_private_student' => false,
            'timezone' => 'America/Chicago',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.create'));

        $response->assertOk();

        $idsJson = $response->viewData('privateStudentIdsJson');
        $contactsJson = $response->viewData('privateFamilyContactsJson');
        $timezonesJson = $response->viewData('schoolTimezonesJson');

        $this->assertIsString($timezonesJson);
        $timezones = json_decode($timezonesJson, true);

        // Both private and regular schools expose their timezone here.
        $this->assertSame('America/Los_Angeles', $timezones[$privateFamily->id]);
        $this->assertSame('America/Chicago', $timezones[$regularSchool->id]);

        $this->assertIsString($idsJson);
        $this->assertIsString($contactsJson);

        $ids = json_decode($idsJson, true);
        $contacts = json_decode($contactsJson, true);

        $this->assertContains($privateFamily->id, $ids);
        $this->assertNotContains($regularSchool->id, $ids);

        $this->assertArrayHasKey($privateFamily->id, $contacts);
        $this->assertSame([
            'name' => "O'Brien Family \"Trust\"",
            'email' => 'obrien@example.com',
            'phone' => '555-123-4567',
            'timezone' => 'America/Los_Angeles',
        ], $contacts[$privateFamily->id]);

        // Verify the rendered HTML attribute is properly escaped — quotes in the JSON
        // become &quot; and the attribute parses back to the same JSON on the client.
        $response->assertSee('id="students-form-data"', false);
        $response->assertSee('data-private-family-contacts="'.e($contactsJson).'"', false);
    }

    public function test_create_form_preselects_school_from_query_param(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.students.create', ['school_id' => $this->school->id])
        );

        $response->assertOk()
            ->assertViewHas('preselectedSchoolId', $this->school->id)
            ->assertViewHas('preselectedTimezone', $this->school->timezone);
    }

    public function test_create_form_ignores_invalid_school_id_query_param(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.students.create', ['school_id' => 999_999])
        );

        $response->assertOk()
            ->assertViewHas('preselectedSchoolId', null)
            ->assertViewHas('preselectedTimezone', null);
    }

    public function test_admin_can_create_student(): void
    {
        Mail::fake();

        $data = [
            'first_name' => 'Ava',
            'middle_name' => 'Rose',
            'last_name' => 'Smith',
            'username' => 'ava.smith.100',
            'email' => 'ava@example.com',
            'gender' => 'Female',
            'date_of_birth' => '2012-03-05',
            'school_id' => $this->school->id,
            'id_number' => 'STU-100',
            'timezone' => 'America/New_York',
            'grade_level' => '5',
            'parent_guardian_name' => 'Mary Smith',
            'parent_guardian_email' => 'mary@example.com',
            'parent_guardian_phone' => '123-456-7890',
            'address' => '123 School St',
            'city' => 'Boston',
            'state' => 'MA',
            'zip_code' => '02115',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), $data);

        $response->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('student_profiles', [
            'first_name' => 'Ava',
            'school_id' => $this->school->id,
        ]);

        // The welcome email is no longer sent on creation; it is now a manual
        // admin action (admin.students.send-welcome-email).
        Mail::assertNotSent(WelcomeStudentMail::class);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.edit', $this->student));

        $response->assertOk()
            ->assertViewIs('admin.students.edit')
            ->assertViewHas('student');
    }

    public function test_admin_can_update_student(): void
    {
        $payload = [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Student',
            'username' => 'updated.student',
            'email' => 'updated@example.com',
            'gender' => 'Male',
            'date_of_birth' => '2011-01-01',
            'school_id' => $this->school->id,
            'id_number' => 'ID-200',
            'timezone' => 'America/Chicago',
            'grade_level' => '6',
            'parent_guardian_name' => 'Guardian',
            'parent_guardian_email' => 'guardian@example.com',
            'parent_guardian_phone' => '222-333-4444',
            'address' => '456 Updated Way',
            'city' => 'Austin',
            'state' => 'TX',
            'zip_code' => '73301',
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.students.update', $this->student), $payload);

        $response->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $this->student->id,
            'first_name' => 'Updated',
            'grade_level' => '6',
        ]);
    }

    public function test_admin_can_change_student_status(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.students.status', $this->student), [
            'status' => 'inactive',
            'reason' => 'Testing',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_export_students(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_filter_students_by_search(): void
    {
        StudentProfile::factory()->create([
            'first_name' => 'UniqueStudent',
            'user_id' => User::factory()->create([
                'name' => 'Unique Student',
                'role' => Role::STUDENT->value,
            ])->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['search' => 'UniqueStudent']));

        $response->assertOk()->assertSee('UniqueStudent');
    }

    public function test_admin_can_filter_students_by_status(): void
    {
        StudentProfile::factory()->create([
            'user_id' => User::factory()->create([
                'role' => Role::STUDENT->value,
                'status' => UserStatus::INACTIVE->value,
            ])->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.index', ['status' => 'inactive']));

        $response->assertOk();
    }

    public function test_create_student_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'username',
            'email',
            'school_id',
            'id_number',
            'timezone',
        ]);
    }

    public function test_create_student_validates_phone_format(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'username' => 'test.phone.format',
            'email' => 'test@example.com',
            'date_of_birth' => '2013-01-01',
            'timezone' => 'America/Chicago',
            'parent_guardian_phone' => '123-456-7890abc', // Invalid: contains letters
        ]);

        $response->assertSessionHasErrors(['parent_guardian_phone']);
    }

    public function test_create_student_accepts_phone_with_digits_and_dashes(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'username' => 'test.phone.valid',
            'email' => 'test@example.com',
            'date_of_birth' => '2013-01-01',
            'timezone' => 'America/Chicago',
            'parent_guardian_phone' => '123-456-7890', // Valid: digits and dashes
        ]);

        $response->assertSessionDoesntHaveErrors(['parent_guardian_phone']);
    }

    public function test_create_student_validates_unique_username(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'username' => $this->student->username,
            'email' => 'test@example.com',
            'date_of_birth' => '2013-01-01',
            'timezone' => 'America/New_York',
        ]);

        $response->assertSessionHasErrors(['username']);
    }

    public function test_non_admin_cannot_modify_students(): void
    {
        $nonAdmin = User::factory()->student()->create();

        $this->actingAs($nonAdmin)->post(route('admin.students.store'), [])
            ->assertForbidden();

        $this->actingAs($nonAdmin)->put(route('admin.students.update', $this->student), [])
            ->assertForbidden();

        $this->actingAs($nonAdmin)->patch(route('admin.students.status', $this->student), [
            'status' => 'inactive',
        ])->assertForbidden();

        $this->actingAs($nonAdmin)->get(route('admin.students.export'))
            ->assertForbidden();
    }

    public function test_admin_can_view_student_show_page_with_dashboard_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.students.show', $this->student));

        $response->assertOk()
            ->assertViewIs('admin.students.show')
            ->assertViewHas('student')
            ->assertViewHas('activeTab', 'dashboard')
            ->assertViewHas('metrics')
            ->assertViewHas('chartData');
    }

    public function test_admin_can_view_student_show_page_with_ssas_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.students.show', [$this->student, 'tab' => 'ssas'])
        );

        $response->assertOk()
            ->assertViewIs('admin.students.show')
            ->assertViewHas('student')
            ->assertViewHas('activeTab', 'ssas')
            ->assertViewHas('ssas')
            ->assertViewHas('ssaFilters')
            ->assertViewHas('statuses')
            ->assertViewHas('students')
            ->assertViewHas('therapists')
            ->assertViewHas('services');
    }

    public function test_admin_can_view_student_show_page_with_therapists_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.students.show', [$this->student, 'tab' => 'therapists'])
        );

        $response->assertOk()
            ->assertViewIs('admin.students.show')
            ->assertViewHas('student')
            ->assertViewHas('activeTab', 'therapists')
            ->assertViewHas('therapists')
            ->assertViewHas('therapistFilters')
            ->assertViewHas('positions');
    }

    public function test_student_show_page_loads_dashboard_metrics_correctly(): void
    {
        $service = \App\Models\Service::factory()->create();
        $therapist = User::factory()->therapist()->create();
        $ssa = \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $therapist->id,
            'status' => \App\Enums\SSAStatus::ACTIVE,
            'tho_minutes' => 2000,
            'served_minutes' => 1000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.students.show', $this->student));

        $response->assertOk();
        $metrics = $response->viewData('metrics');
        $this->assertEquals(1, $metrics['total_ssas']);
        $this->assertEquals(1, $metrics['active_ssas']);

        $chartData = $response->viewData('chartData');
        $this->assertArrayHasKey('served_hours', $chartData);
        $this->assertArrayHasKey('remaining_hours', $chartData);
        $this->assertArrayHasKey('progress', $chartData);
    }

    public function test_student_show_page_filters_therapists_by_search(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.students.show', [$this->student, 'tab' => 'therapists', 'search' => 'John'])
        );

        $response->assertOk();
        $response->assertViewHas('therapists');
        $response->assertViewHas('datatableUrl');
        $response->assertViewHas('studentId', $this->student->id);
    }

    public function test_student_show_page_filters_therapists_by_status(): void
    {
        $activeTherapist = User::factory()->therapist()->create(['status' => UserStatus::ACTIVE]);
        $inactiveTherapist = User::factory()->therapist()->create(['status' => UserStatus::INACTIVE]);

        $service = \App\Models\Service::factory()->create();
        \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $activeTherapist->id,
        ]);
        \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $inactiveTherapist->id,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.students.show', [$this->student, 'tab' => 'therapists', 'status' => 'active'])
        );

        $response->assertOk();
        $therapists = $response->viewData('therapists');
        $this->assertTrue($therapists->every(fn ($therapist) => $therapist->status === UserStatus::ACTIVE));
    }

    public function test_student_show_page_filters_therapists_by_position(): void
    {
        $slpPosition = Position::firstOrCreate(['name' => 'SLP'], ['status' => 'active']);
        $otPosition = Position::firstOrCreate(['name' => 'OT'], ['status' => 'active']);

        $slpTherapist = User::factory()->therapist()->has(
            \App\Models\TherapistProfile::factory()->state(['position_id' => $slpPosition->id])
        )->create();
        $otTherapist = User::factory()->therapist()->has(
            \App\Models\TherapistProfile::factory()->state(['position_id' => $otPosition->id])
        )->create();

        $service = \App\Models\Service::factory()->create();
        \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $slpTherapist->id,
        ]);
        \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $this->student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $otTherapist->id,
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.students.show', [$this->student, 'tab' => 'therapists', 'position_id' => $slpPosition->id])
        );

        $response->assertOk();
        $therapists = $response->viewData('therapists');
        $this->assertTrue($therapists->every(function ($therapist) use ($slpPosition) {
            return $therapist->therapistProfile->position_id === $slpPosition->id;
        }));
    }

    public function test_non_admin_cannot_view_student_show_page(): void
    {
        $nonAdmin = User::factory()->student()->create();

        $response = $this->actingAs($nonAdmin)->get(route('admin.students.show', $this->student));

        $response->assertForbidden();
    }
}
