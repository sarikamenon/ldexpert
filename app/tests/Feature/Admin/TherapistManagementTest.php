<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\WelcomeTherapistMail;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class TherapistManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $therapist;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->admin()->create();
        $this->therapist = User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $this->manager->id]), 'therapistProfile')
            ->create();
    }

    public function test_admin_can_view_therapists_list(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.therapists.index'));

        $response->assertOk();
        $response->assertViewIs('admin.therapists.index');
        $response->assertViewHas('therapists');
        $response->assertViewHas('metrics');
        $response->assertViewHas('filters');
        $response->assertViewHas('positions');
    }

    public function test_non_admin_cannot_view_therapists_list(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('admin.therapists.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_create_therapist_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.therapists.create'));

        $response->assertOk();
        $response->assertViewIs('admin.therapists.create');
        $response->assertViewHas('titles');
        $response->assertViewHas('positions');
        $response->assertViewHas('states');
        $response->assertViewHas('timezones');
        $response->assertViewHas('managers');
    }

    public function test_admin_can_create_therapist(): void
    {
        Mail::fake();

        $therapistData = [
            'employee_type' => 'W2',
            'title' => 'Dr.',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'personal_email' => 'jane.smith@example.com',
            'phone' => '555-123-4567',
            'ld_email' => 'jane.smith@ldexpert.com',
            'address' => '123 Test St',
            'comments' => 'Test comment',
            'position' => 'SLP',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => $this->manager->id,
            'max_weekly_hours' => 40,
            'dob' => '1990-01-01',
            'default_meeting_location' => 'https://meet.google.com/new',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.therapists.store'), $therapistData);

        $response->assertRedirect(route('admin.therapists.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('therapist_profiles', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'personal_email' => 'jane.smith@example.com',
            'max_weekly_hours' => 40,
            'default_meeting_location' => 'https://meet.google.com/new',
        ]);

        // Verify welcome email was sent
        Mail::assertSent(WelcomeTherapistMail::class, function ($mail) use ($therapistData) {
            return $mail->hasTo($therapistData['personal_email']);
        });
    }

    public function test_admin_can_view_edit_therapist_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.therapists.edit', $this->therapist));

        $response->assertOk();
        $response->assertViewIs('admin.therapists.edit');
        $response->assertViewHas('therapist');
        $response->assertViewHas('titles');
        $response->assertViewHas('positions');
        $response->assertViewHas('states');
        $response->assertViewHas('timezones');
        $response->assertViewHas('managers');
    }

    public function test_admin_can_update_therapist(): void
    {
        $updateData = [
            'employee_type' => '1099',
            'title' => 'Mrs.',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'personal_email' => 'updated@example.com',
            'phone' => '555-999-8888',
            'ld_email' => 'updated@ldexpert.com',
            'address' => '456 New St',
            'comments' => 'Updated comment',
            'position' => 'OT',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'manager_id' => $this->manager->id,
            'max_weekly_hours' => 32,
            'dob' => '1985-05-15',
            'default_meeting_location' => 'https://meet.google.com/updated',
        ];

        $response = $this->actingAs($this->admin)->put(
            route('admin.therapists.update', $this->therapist),
            $updateData
        );

        $response->assertRedirect(route('admin.therapists.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $this->therapist->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'personal_email' => 'updated@example.com',
            'max_weekly_hours' => 32,
            'default_meeting_location' => 'https://meet.google.com/updated',
        ]);
    }

    public function test_admin_can_change_therapist_status(): void
    {
        $this->therapist->update(['status' => UserStatus::ACTIVE]);

        $response = $this->actingAs($this->admin)->patch(
            route('admin.therapists.status', $this->therapist),
            [
                'status' => 'inactive',
                'reason' => 'Extended leave',
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $this->therapist->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_export_therapists(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.therapists.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');
    }

    public function test_admin_can_filter_therapists_by_search(): void
    {
        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state([
                'first_name' => 'Unique',
                'last_name' => 'Therapist',
                'manager_id' => $this->manager->id,
            ]), 'therapistProfile')
            ->create(['name' => 'Unique Therapist']);

        $response = $this->actingAs($this->admin)->get(route('admin.therapists.index', ['search' => 'Unique']));

        $response->assertOk();
        $response->assertSee('Unique');
    }

    public function test_admin_can_filter_therapists_by_status(): void
    {
        User::factory()
            ->therapist()
            ->has(TherapistProfile::factory()->state(['manager_id' => $this->manager->id]), 'therapistProfile')
            ->create(['status' => UserStatus::INACTIVE]);

        $response = $this->actingAs($this->admin)->get(route('admin.therapists.index', ['status' => 'inactive']));

        $response->assertOk();
    }

    public function test_create_therapist_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.therapists.store'), []);

        $response->assertSessionHasErrors([
            'employee_type',
            'title',
            'first_name',
            'last_name',
            'personal_email',
            'phone',
            'position',
            'state',
            'timezone',
            'manager_id',
            'max_weekly_hours',
        ]);
    }

    public function test_create_therapist_validates_phone_format(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.therapists.store'), [
            'employee_type' => 'W2',
            'title' => 'Dr.',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'personal_email' => 'jane@example.com',
            'phone' => '1234567890', // Invalid format
            'position' => 'SLP',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => $this->manager->id,
            'max_weekly_hours' => 35,
        ]);

        $response->assertSessionHasErrors(['phone']);
    }

    public function test_create_therapist_validates_unique_email(): void
    {
        $existingEmail = $this->therapist->therapistProfile->personal_email;

        $response = $this->actingAs($this->admin)->post(route('admin.therapists.store'), [
            'employee_type' => 'W2',
            'title' => 'Dr.',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'personal_email' => $existingEmail, // Duplicate
            'phone' => '555-123-4567',
            'position' => 'SLP',
            'state' => 'CA',
            'timezone' => 'America/Los_Angeles',
            'manager_id' => $this->manager->id,
            'max_weekly_hours' => 40,
        ]);

        $response->assertSessionHasErrors(['personal_email']);
    }

    public function test_non_admin_cannot_create_therapist(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('admin.therapists.store'), []);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_update_therapist(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->put(route('admin.therapists.update', $this->therapist), []);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_change_therapist_status(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->patch(route('admin.therapists.status', $this->therapist), [
            'status' => 'inactive',
            'reason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_export_therapists(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('admin.therapists.export'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_therapist_show_page_with_dashboard_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.therapists.show', $this->therapist));

        $response->assertOk();
        $response->assertViewIs('admin.therapists.show');
        $response->assertViewHas('therapist');
        $response->assertViewHas('activeTab', 'dashboard');
        $response->assertViewHas('metrics');
        $response->assertViewHas('chartData');
    }

    public function test_admin_can_view_therapist_show_page_with_students_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.therapists.show', [$this->therapist, 'tab' => 'students'])
        );

        $response->assertOk();
        $response->assertViewIs('admin.therapists.show');
        $response->assertViewHas('therapist');
        $response->assertViewHas('activeTab', 'students');
        $response->assertViewHas('students');
        $response->assertViewHas('studentFilters');
        $response->assertViewHas('schools');
        $response->assertViewHas('statuses');
    }

    public function test_admin_can_view_therapist_show_page_with_ssas_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.therapists.show', [$this->therapist, 'tab' => 'ssas'])
        );

        $response->assertOk();
        $response->assertViewIs('admin.therapists.show');
        $response->assertViewHas('therapist');
        $response->assertViewHas('activeTab', 'ssas');
        $response->assertViewHas('ssas');
        $response->assertViewHas('ssaFilters');
        $response->assertViewHas('statuses');
        $response->assertViewHas('students');
        $response->assertViewHas('therapists');
        $response->assertViewHas('services');
    }

    public function test_therapist_show_page_loads_dashboard_metrics_correctly(): void
    {
        $student = User::factory()->student()->create();
        $service = \App\Models\Service::factory()->create();
        $ssa = \App\Models\ServiceSupportAgreement::factory()->create([
            'student_id' => $student->id,
            'primary_service_id' => $service->id,
            'assigned_therapist_id' => $this->therapist->id,
            'status' => \App\Enums\SSAStatus::ACTIVE,
            'tho_minutes' => 1000,
            'served_minutes' => 500,
        ]);

        // Link student to therapist
        $this->therapist->students()->attach($student->id);

        $response = $this->actingAs($this->admin)->get(route('admin.therapists.show', $this->therapist));

        $response->assertOk();
        $metrics = $response->viewData('metrics');
        $this->assertEquals(1, $metrics['total_students']);
        $this->assertEquals(1, $metrics['active_ssas']);

        $chartData = $response->viewData('chartData');
        $this->assertArrayHasKey('served', $chartData);
        $this->assertArrayHasKey('remaining', $chartData);
        $this->assertArrayHasKey('progress', $chartData);
    }

    public function test_therapist_show_page_filters_students_by_search(): void
    {
        $student1 = User::factory()->student()->create(['name' => 'John Doe']);
        $student2 = User::factory()->student()->create(['name' => 'Jane Smith']);

        $this->therapist->students()->attach([$student1->id, $student2->id]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.therapists.show', [$this->therapist, 'tab' => 'students', 'search' => 'John'])
        );

        $response->assertOk();
        $students = $response->viewData('students');
        $this->assertTrue($students->contains('name', 'John Doe'));
    }

    public function test_therapist_show_page_filters_students_by_status(): void
    {
        $activeStudent = User::factory()->student()->create(['status' => UserStatus::ACTIVE]);
        $inactiveStudent = User::factory()->student()->create(['status' => UserStatus::INACTIVE]);

        $this->therapist->students()->attach([$activeStudent->id, $inactiveStudent->id]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.therapists.show', [$this->therapist, 'tab' => 'students', 'status' => 'active'])
        );

        $response->assertOk();
        $students = $response->viewData('students');
        $this->assertTrue($students->every(fn($student) => $student->status === UserStatus::ACTIVE));
    }

    public function test_non_admin_cannot_view_therapist_show_page(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('admin.therapists.show', $this->therapist));

        $response->assertForbidden();
    }
}
