<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Tests\TestCase;

// uses(TestCase::class, RefreshDatabase::class);

function ssaAdmin(): User
{
    return User::factory()->admin()->create();
}

function ssaStudent(): User
{
    return User::factory()->create([
        'role' => Role::STUDENT,
        'status' => UserStatus::ACTIVE,
    ]);
}

function ssaTherapist(): User
{
    return User::factory()->create([
        'role' => Role::THERAPIST,
        'status' => UserStatus::ACTIVE,
    ]);
}

function ssaService(): Service
{
    return Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
        'is_direct_service' => true,
    ]);
}

function ssaIndirectService(): Service
{
    return Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
        'is_direct_service' => false,
    ]);
}

function ssaPayload(array $overrides = []): array
{
    $student = ssaStudent();
    $service = ssaService();

    return array_merge([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'end_date' => now()->addDays(365)->format('Y-m-d'),
        // Use a non-5-multiple value to ensure SSA accepts arbitrary minute values
        'minutes_per_session' => 37,
        'frequency' => ServiceFrequency::WEEKLY->value,
        'sessions_per_frequency' => 2,
        'tho_minutes' => 3120,
        'assigned_therapist_id' => null,
    ], $overrides);
}

test('allows admin to view SSAs index', function () {
    $admin = ssaAdmin();
    ServiceSupportAgreement::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.ssas.index'));

    $response->assertOk()
        ->assertSee('SSA')
        ->assertViewIs('admin.ssas.index')
        ->assertViewHas('ssas')
        ->assertViewHas('metrics')
        ->assertViewHas('filters')
        ->assertViewHas('statuses')
        ->assertViewHas('students')
        ->assertViewHas('services')
        ->assertViewHas('therapists');
});

test('prevents non-admin access to SSAs index', function () {
    $therapist = ssaTherapist();

    $this->actingAs($therapist)
        ->get(route('admin.ssas.index'))
        ->assertForbidden();
});

test('allows admin to view create SSA form', function () {
    $admin = ssaAdmin();
    ssaStudent();
    ssaService();

    $this->actingAs($admin)
        ->get(route('admin.ssas.create'))
        ->assertOk()
        ->assertSee('Create SSA');
});

test('allows admin to create SSA without therapist', function () {
    $admin = ssaAdmin();
    $payload = ssaPayload();

    $this->actingAs($admin)
        ->post(route('admin.ssas.store'), $payload)
        ->assertRedirect(route('admin.ssas.index'))
        ->assertSessionHas('status', 'SSA created successfully.');

    $this->assertDatabaseHas('service_support_agreements', [
        'student_id' => $payload['student_id'],
        'primary_service_id' => $payload['primary_service_id'],
        'status' => SSAStatus::PENDING->value,
        'assigned_therapist_id' => null,
    ]);
});

test('normalizes one time frequency fields when creating an SSA', function () {
    $admin = ssaAdmin();
    $frequencyService = Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
        'is_direct_service' => true,
        'is_frequency_service' => true,
    ]);
    $payload = ssaPayload([
        'primary_service_id' => $frequencyService->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-01',
        'minutes_per_session' => 45,
        'frequency' => ServiceFrequency::ONE_TIME->value,
        'sessions_per_frequency' => 6,
        'calculated_minutes' => 270,
        'tho_minutes' => 45,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.store'), $payload)
        ->assertRedirect(route('admin.ssas.index'))
        ->assertSessionHas('status', 'SSA created successfully.');

    $this->assertDatabaseHas('service_support_agreements', [
        'student_id' => $payload['student_id'],
        'primary_service_id' => $frequencyService->id,
        'frequency' => ServiceFrequency::ONE_TIME->value,
        'sessions_per_frequency' => 1,
        'calculated_minutes' => 45,
        'tho_minutes' => 2700,
    ]);
});

test('allows updating an SSA to one time with the same start and end date', function () {
    $admin = ssaAdmin();
    $frequencyService = Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
        'is_direct_service' => true,
        'is_frequency_service' => true,
    ]);
    $ssa = ServiceSupportAgreement::factory()->create([
        'primary_service_id' => $frequencyService->id,
        'frequency' => ServiceFrequency::WEEKLY,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'minutes_per_session' => 30,
        'sessions_per_frequency' => 2,
        'tho_minutes' => 3120,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.ssas.update', $ssa), [
            'primary_service_id' => $ssa->primary_service_id,
            'start_date' => '2026-02-15',
            'end_date' => '2026-02-15',
            'minutes_per_session' => 50,
            'frequency' => ServiceFrequency::ONE_TIME->value,
            'sessions_per_frequency' => 4,
            'calculated_minutes' => 200,
            'tho_minutes' => 50,
        ])
        ->assertRedirect(route('admin.ssas.index'))
        ->assertSessionHas('status', 'SSA updated successfully.');

    $ssa->refresh();

    expect($ssa->frequency)->toBe(ServiceFrequency::ONE_TIME)
        ->and($ssa->start_date->format('Y-m-d'))->toBe('2026-02-15')
        ->and($ssa->end_date->format('Y-m-d'))->toBe('2026-02-15')
        ->and($ssa->sessions_per_frequency)->toBe(1)
        ->and($ssa->calculated_minutes)->toBe(50)
        ->and($ssa->tho_minutes)->toBe(3000);
});

test('allows admin to create SSA with therapist', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $payload = ssaPayload(['assigned_therapist_id' => $therapist->id]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.store'), $payload)
        ->assertRedirect(route('admin.ssas.index'))
        ->assertSessionHas('status', 'SSA created successfully.');

    $this->assertDatabaseHas('service_support_agreements', [
        'student_id' => $payload['student_id'],
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    // Check assignment history was created
    $ssa = ServiceSupportAgreement::where('student_id', $payload['student_id'])->first();
    $this->assertDatabaseHas('ssa_assignment_history', [
        'ssa_id' => $ssa->id,
        'therapist_id' => $therapist->id,
        'action' => 'assigned',
    ]);
});

test('prevents activating SSA without therapist', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.status', $ssa), [
            'status' => SSAStatus::ACTIVE->value,
        ])
        ->assertStatus(422);
});

test('allows activating SSA with therapist', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.status', $ssa), [
            'status' => SSAStatus::ACTIVE->value,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);
});

test('allows assigning therapist to SSA', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.assign-therapist', $ssa), [
            'therapist_id' => $therapist->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'assigned_therapist_id' => $therapist->id,
    ]);

    $this->assertDatabaseHas('ssa_assignment_history', [
        'ssa_id' => $ssa->id,
        'therapist_id' => $therapist->id,
        'action' => 'assigned',
    ]);
});

test('allows unassigning therapist from SSA', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.unassign-therapist', $ssa), [
            'reason' => 'Therapist unavailable',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->assertDatabaseHas('ssa_assignment_history', [
        'ssa_id' => $ssa->id,
        'therapist_id' => $therapist->id,
        'action' => 'unassigned',
    ]);
});

test('prevents changing primary service after creation', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create();
    $newService = ssaService();

    $this->actingAs($admin)
        ->put(route('admin.ssas.update', $ssa), [
            'primary_service_id' => $newService->id,
            'start_date' => $ssa->start_date->format('Y-m-d'),
            'end_date' => $ssa->end_date->format('Y-m-d'),
        ])
        ->assertRedirect();

    // Primary service should not change
    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'primary_service_id' => $ssa->primary_service_id,
    ]);
});

test('allows admin to view SSA show page with dashboard tab', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.ssas.show', $ssa))
        ->assertOk()
        ->assertViewIs('admin.ssas.show')
        ->assertViewHas('ssa')
        ->assertViewHas('activeTab', 'dashboard');
});

test('allows admin to view SSA show page with assignment tab', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ssas.show', [$ssa, 'tab' => 'assignment']))
        ->assertOk()
        ->assertViewIs('admin.ssas.show')
        ->assertViewHas('ssa')
        ->assertViewHas('activeTab', 'assignment')
        ->assertViewHas('assignmentHistory');
});

test('loads assignment history correctly for SSA show page', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
    ]);

    // Create assignment history manually since we may not have a factory
    \App\Models\SSAAssignmentHistory::create([
        'ssa_id' => $ssa->id,
        'therapist_id' => $therapist->id,
        'assigned_by' => $admin->id,
        'action' => 'assigned',
        'assigned_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.ssas.show', [$ssa, 'tab' => 'assignment']));

    $response->assertOk();
    $assignmentHistory = $response->viewData('assignmentHistory');
    expect($assignmentHistory)->not->toBeEmpty();
});

test('prevents non-admin from viewing SSA show page', function () {
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create();

    $this->actingAs($therapist)
        ->get(route('admin.ssas.show', $ssa))
        ->assertForbidden();
});

test('show page renders assign therapist button in header when SSA has no therapist', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ssas.show', $ssa))
        ->assertOk()
        ->assertSee('assign-therapist-btn', false)
        ->assertSee('Assign Therapist');
});

test('show page does not render assign therapist button when therapist is already assigned', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ssas.show', $ssa))
        ->assertOk()
        ->assertDontSee('assign-therapist-btn', false);
});

test('prevents non-admin from assigning therapist to SSA', function () {
    $therapist = ssaTherapist();
    $anotherTherapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($therapist)
        ->post(route('admin.ssas.assign-therapist', $ssa), [
            'therapist_id' => $anotherTherapist->id,
        ])
        ->assertForbidden();
});

test('prevents non-admin from unassigning therapist from SSA', function () {
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $this->actingAs($therapist)
        ->post(route('admin.ssas.unassign-therapist', $ssa), [
            'reason' => 'Test',
        ])
        ->assertForbidden();
});

test('assigning therapist to pending SSA auto-activates it', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.assign-therapist', $ssa), [
            'therapist_id' => $therapist->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);
});

test('allows deactivating a pending SSA', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.status', $ssa), [
            'status' => SSAStatus::DEACTIVATED->value,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'status' => SSAStatus::DEACTIVATED->value,
    ]);
});

test('allows deactivating an active SSA', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.status', $ssa), [
            'status' => SSAStatus::DEACTIVATED->value,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'status' => SSAStatus::DEACTIVATED->value,
    ]);
});

test('prevents deactivating a completed SSA', function () {
    $admin = ssaAdmin();
    $ssa = ServiceSupportAgreement::factory()->create([
        'status' => SSAStatus::COMPLETED->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.ssas.status', $ssa), [
            'status' => SSAStatus::DEACTIVATED->value,
        ])
        ->assertStatus(422);
});

test('unassigning therapist reverts SSA to pending status', function () {
    $admin = ssaAdmin();
    $therapist = ssaTherapist();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.unassign-therapist', $ssa), [])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);
});
