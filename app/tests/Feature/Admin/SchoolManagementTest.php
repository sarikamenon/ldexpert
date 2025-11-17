<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUser(): User
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
    $admin = adminUser();
    School::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.schools.index'))
        ->assertOk()
        ->assertSee('Schools');
});

it('prevents non-admin from accessing schools', function () {
    $therapist = User::factory()->therapist()->create();

    $this->actingAs($therapist)
        ->get(route('admin.schools.index'))
        ->assertForbidden();
});

it('creates a school', function () {
    $admin = adminUser();
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
    $admin = adminUser();
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
    $admin = adminUser();
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
    $admin = adminUser();
    $school = School::factory()->create(['display_name' => 'Export School']);

    $response = $this->actingAs($admin)->get(route('admin.schools.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('Export School');
});
