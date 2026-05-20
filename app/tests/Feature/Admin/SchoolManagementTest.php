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
        ->assertSessionHas('status', 'School/family added successfully.');

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
        ->assertSessionHas('status', 'School/family information updated successfully.');

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'display_name' => 'Updated Name',
    ]);
});

it('persists allow_weekend_scheduling flag on update', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['allow_weekend_scheduling' => false]);

    $payload = validSchoolPayload($admin->id);
    $payload['allow_weekend_scheduling'] = '1';

    $this->actingAs($admin)
        ->patch(route('admin.schools.update', $school), $payload)
        ->assertRedirect(route('admin.schools.index'));

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'allow_weekend_scheduling' => 1,
    ]);
});

it('defaults allow_weekend_scheduling to false when checkbox unchecked', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['allow_weekend_scheduling' => true]);

    $payload = validSchoolPayload($admin->id);
    $payload['allow_weekend_scheduling'] = '0';

    $this->actingAs($admin)
        ->patch(route('admin.schools.update', $school), $payload)
        ->assertRedirect(route('admin.schools.index'));

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'allow_weekend_scheduling' => 0,
    ]);
});

it('changes school status with reason', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patchJson(route('admin.schools.status', $school), [
            'status' => 'inactive',
            'reason' => 'Testing toggle',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'School/family deactivated successfully.',
        ]);

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

it('shows Yes on overview Characteristics when weekend scheduling is allowed', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['allow_weekend_scheduling' => true]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'overview']));

    $response->assertOk()
        ->assertSeeText('Allow weekend scheduling?')
        ->assertSeeText('Yes');
});

it('shows No on overview Characteristics when weekend scheduling is not allowed', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['allow_weekend_scheduling' => false]);

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'overview']));

    $response->assertOk()
        ->assertSeeText('Allow weekend scheduling?')
        ->assertSeeText('No');
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

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'ssas']));

    $response->assertOk()
        ->assertViewHas('ssas')
        ->assertViewHas('datatableUrl')
        ->assertViewHas('schoolId', $school->id);
});

it('shows only therapists linked to a school on therapists tab', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.schools.show', [$school, 'tab' => 'therapists']));

    $response->assertOk()
        ->assertViewHas('therapists')
        ->assertViewHas('datatableUrl')
        ->assertViewHas('schoolId', $school->id);
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

it('saves is_auto_extend when creating a school', function () {
    $admin = schoolAdminUser();
    $payload = array_merge(validSchoolPayload($admin->id), [
        'is_private_student' => true,
        'is_auto_extend' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.schools.store'), $payload)
        ->assertRedirect(route('admin.schools.index'));

    $this->assertDatabaseHas('schools', [
        'display_name' => $payload['display_name'],
        'is_auto_extend' => true,
    ]);
});

it('saves is_auto_extend when updating a school', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['is_private_student' => true, 'is_auto_extend' => false]);

    $payload = array_merge(validSchoolPayload($admin->id), [
        'display_name' => $school->display_name,
        'is_private_student' => true,
        'is_auto_extend' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.schools.update', $school), $payload)
        ->assertRedirect(route('admin.schools.index'));

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'is_auto_extend' => true,
    ]);
});

it('stores is_auto_extend as false when unchecked', function () {
    $admin = schoolAdminUser();
    $school = School::factory()->create(['is_private_student' => true, 'is_auto_extend' => true]);

    $payload = array_merge(validSchoolPayload($admin->id), [
        'display_name' => $school->display_name,
        'is_private_student' => false,
        'is_auto_extend' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.schools.update', $school), $payload)
        ->assertRedirect(route('admin.schools.index'));

    $this->assertDatabaseHas('schools', [
        'id' => $school->id,
        'is_auto_extend' => false,
    ]);
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
