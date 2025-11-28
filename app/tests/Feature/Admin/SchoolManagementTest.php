<?php

use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function schoolAdminUser(): User
{
    return User::factory()->admin()->create();
}

function validSchoolPayload(int $managerId): array
{
    return [
        'full_name' => 'North Ridge Academy',
        'display_name' => 'North Ridge',
        'address' => '123 Main St',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => $managerId,
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-555-5555',
        'contact_email' => 'contact@laravel.com',
        'invoice_email' => 'billing@laravel.com',
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'non_billable_scheduling' => false,
        'external_emr_name' => 'EMR X',
    ];
}

it('allows admin to view schools index', function () {
    $admin = schoolAdminUser();
    School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.index'));

    $response->assertOk()
        ->assertSee('Schools')
        ->assertViewIs('admin.schools.index')
        ->assertViewHas('schools')
        ->assertViewHas('metrics')
        ->assertViewHas('filters')
        ->assertViewHas('states')
        ->assertViewHas('timezones')
        ->assertViewHas('managers')
        ->assertViewHas('schoolTypes');
});

it('prevents non-admin from accessing schools', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.schools.index'))
        ->assertForbidden();
});

it('creates a school', function () {
    $admin = schoolAdminUser();
    $payload = validSchoolPayload($admin->id);

    $this->actingAs($admin)
        ->post(route('admin.schools.store'), $payload)
        ->assertRedirect(route('admin.schools.index'))
        ->assertSessionHas('status', 'School added successfully.');

    $this->assertDatabaseHas('schools', [
        'display_name' => 'North Ridge',
        'manager_id' => $admin->id,
    ]);
});

it('updates a school', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $payload = validSchoolPayload($admin->id);
    $payload['display_name'] = 'Updated Name';

    $this->actingAs($admin)
        ->patch(route('admin.schools.update', $school), $payload)
        ->assertRedirect(route('admin.schools.index'))
        ->assertSessionHas('status', 'School information updated successfully.');

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'display_name' => 'Updated Name',
    ]);
});

it('changes school status with reason', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('admin.schools.status', $school), [
            'status' => 'inactive',
            'reason' => 'Testing toggle',
        ])
        ->assertRedirect(route('admin.schools.index'))
        ->assertSessionHas('status', 'School deactivated successfully.');

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'status' => 'inactive',
        'status_reason' => 'Testing toggle',
    ]);
});

it('exports schools as csv', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['display_name' => 'Export School']);

    $response = $this->actingAs($admin)->get(route('admin.schools.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('Export School');
});

it('allows admin to view school show page with dashboard tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', $school));

    $response->assertOk()
        ->assertViewIs('admin.schools.show')
        ->assertViewHas('school')
        ->assertViewHas('activeTab', 'dashboard')
        ->assertViewHas('metrics')
        ->assertViewHas('statusCounts')
        ->assertViewHas('chartData');
});

it('allows admin to view school show page with students tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'students']));

    $response->assertOk()
        ->assertViewIs('admin.schools.show')
        ->assertViewHas('school')
        ->assertViewHas('activeTab', 'students')
        ->assertViewHas('students')
        ->assertViewHas('studentFilters')
        ->assertViewHas('schools')
        ->assertViewHas('statuses');
});

it('allows admin to view school show page with therapists tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'therapists']));

    $response->assertOk()
        ->assertViewIs('admin.schools.show')
        ->assertViewHas('school')
        ->assertViewHas('activeTab', 'therapists')
        ->assertViewHas('therapists')
        ->assertViewHas('therapistFilters')
        ->assertViewHas('positions');
});

it('allows admin to view school show page with ssas tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'ssas']));

    $response->assertOk()
        ->assertViewIs('admin.schools.show')
        ->assertViewHas('school')
        ->assertViewHas('activeTab', 'ssas')
        ->assertViewHas('ssas')
        ->assertViewHas('ssaFilters')
        ->assertViewHas('statuses')
        ->assertViewHas('students')
        ->assertViewHas('therapists')
        ->assertViewHas('services');
});

it('shows only SSAs belonging to the school on ssas tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $service = Service::factory()->create();

    $studentInSchool = User::factory()->student()->create();
    $studentInSchool->studentProfile->update(['school_id' => $school->id]);

    $studentInOtherSchool = User::factory()->student()->create();
    $studentInOtherSchool->studentProfile->update(['school_id' => $otherSchool->id]);

    ServiceSupportAgreement::factory()->create([
        'student_id' => $studentInSchool->id,
        'primary_service_id' => $service->id,
    ]);

    ServiceSupportAgreement::factory()->create([
        'student_id' => $studentInOtherSchool->id,
        'primary_service_id' => $service->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'ssas']));

    /** @var \Illuminate\Pagination\LengthAwarePaginator $ssas */
    $ssas = $response->viewData('ssas');

    expect($ssas->total())->toBe(1)
        ->and(collect($ssas->items())->first()->student_id)->toBe($studentInSchool->id);
});

it('shows only therapists linked to a school on therapists tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $student->studentProfile->update(['school_id' => $school->id]);

    $linkedTherapist = User::factory()->therapist()->create(['name' => 'Linked Therapist']);
    $otherTherapist = User::factory()->therapist()->create(['name' => 'Other Therapist']);

    $linkedTherapist->students()->attach($student->id, [
        'assigned_at' => now(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'therapists']));

    $therapists = $response->viewData('therapists');
    expect($therapists)->toHaveCount(1)
        ->and($therapists->first()->id)->toBe($linkedTherapist->id);

    $response->assertSee('Linked Therapist')
        ->assertDontSee('Other Therapist');
});

it('includes SSA-assigned therapists for school SSA tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $student->studentProfile->update(['school_id' => $school->id]);
    $service = Service::factory()->create();

    $ssaTherapist = User::factory()->therapist()->create(['name' => 'SSA Therapist']);
    $otherTherapist = User::factory()->therapist()->create(['name' => 'Other Therapist']);

    ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $ssaTherapist->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'ssas']));

    $therapists = $response->viewData('therapists');
    expect($therapists->pluck('id'))->toContain($ssaTherapist->id)
        ->and($therapists->pluck('id'))->not->toContain($otherTherapist->id);

    $response->assertSee('SSA Therapist')
        ->assertDontSee('Other Therapist');
});

it('prevents non-admin from viewing school show page', function () {
    $therapist = User::factory()->therapist()->create();
    $school = School::factory()->create();

    $this->actingAs($therapist)
        ->get(route('admin.schools.show', $school))
        ->assertForbidden();
});

it('loads dashboard metrics correctly for school show page', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    $therapist = User::factory()->therapist()->create();

    // Create student profile linked to school
    \App\Models\StudentProfile::factory()->create([
        'user_id' => $student->id,
        'school_id' => $school->id,
    ]);

    // Create SSA for the student
    $service = \App\Models\Service::factory()->create();
    $ssa = \App\Models\ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => \App\Enums\SSAStatus::ACTIVE,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', $school));

    $response->assertOk();
    $metrics = $response->viewData('metrics');
    expect($metrics['total_students'])->toBe(1)
        ->and($metrics['total_ssas'])->toBe(1);
});
