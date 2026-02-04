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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        'additional_service_ids' => [],
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

test('stores indirect additional services for SSA', function () {
    $admin = ssaAdmin();
    $indirectA = ssaIndirectService();
    $indirectB = ssaIndirectService();
    $payload = ssaPayload([
        'additional_service_ids' => [$indirectA->id, $indirectB->id],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ssas.store'), $payload)
        ->assertRedirect(route('admin.ssas.index'));

    $ssa = ServiceSupportAgreement::with('additionalServices')->first();
    expect($ssa)->not->toBeNull();
    expect($ssa->additionalServices->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$indirectA->id, $indirectB->id])->sort()->values()->all());

    $primaryServiceId = DB::table('ssa_services')
        ->where('ssa_id', $ssa->id)
        ->where('is_primary', true)
        ->value('service_id');

    expect($primaryServiceId)->toBe($payload['primary_service_id']);

    $storedIndirects = DB::table('ssa_services')
        ->where('ssa_id', $ssa->id)
        ->where('is_primary', false)
        ->pluck('service_id')
        ->sort()
        ->values()
        ->all();

    expect($storedIndirects)->toEqual(
        collect([$indirectA->id, $indirectB->id])->sort()->values()->all()
    );
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
        ->assertViewHas('assignmentHistory')
        ->assertViewHas('therapists');
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
