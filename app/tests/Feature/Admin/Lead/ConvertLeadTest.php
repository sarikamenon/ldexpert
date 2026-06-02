<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function convertAdmin(): User
{
    return User::factory()->admin()->create();
}

function basePayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'new.student',
        'email' => 'new.student@example.com',
        'timezone' => 'America/New_York',
    ], $overrides);
}

it('converts a lead into a student using an existing private school with optional fields blank', function () {
    $admin = convertAdmin();
    $school = School::factory()->private()->create();
    $lead = Lead::factory()->create(['school_id' => null]);

    $response = $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload(['school_id' => $school->id])
    );

    $profile = StudentProfile::where('school_id', $school->id)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->id_number)->not->toBeNull(); // auto-generated for private

    $response->assertRedirect(route('admin.students.show', $profile->user_id));

    $lead->refresh();
    expect($lead->status)->toBe(LeadStatus::ENROLLED)
        ->and($lead->converted_student_id)->toBe($profile->user_id)
        ->and($lead->converted_at)->not->toBeNull();
});

it('requires a student id when an existing non-private school is picked', function () {
    $admin = convertAdmin();
    $school = School::factory()->nonPrivate()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload(['school_id' => $school->id])
    );

    $response->assertSessionHasErrors('id_number');
});

it('creates a private family from the lead when no school is picked and the box is checked', function () {
    $admin = convertAdmin();
    $lead = Lead::factory()->create([
        'school_id' => null,
        'parent_guardian_name' => 'Maria Rivera',
        'parent_guardian_email' => 'maria@example.com',
    ]);

    $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload([
            'create_private_family' => '1',
            'family_full_name' => 'Rivera Family',
            'family_name' => 'Rivera Family',
            'family_school_type' => 'Virtual',
            'family_state' => 'MA',
            'family_timezone' => 'America/New_York',
            'family_contact_first_name' => 'Maria',
            'family_contact_email' => 'maria@example.com',
        ])
    );

    $school = School::where('display_name', 'Rivera Family')->first();

    expect($school)->not->toBeNull()
        ->and($school->is_private_student)->toBeTrue()
        ->and($school->school_type)->toBe('Virtual')
        ->and($school->getRawOriginal('state'))->toBe('MA')
        ->and((int) $school->manager_id)->toBe($admin->id);

    $profile = StudentProfile::where('school_id', $school->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->id_number)->not->toBeNull();
});

it('creates a normal (non-private) school when no school is picked and the box is unchecked', function () {
    $admin = convertAdmin();
    $lead = Lead::factory()->create(['school_id' => null]);

    $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload([
            'family_full_name' => 'North Ridge Academy',
            'family_name' => 'North Ridge Academy',
            'family_school_type' => 'Brick Mortar',
            'family_state' => 'MA',
            'family_timezone' => 'America/New_York',
            'id_number' => 'STU-100',
        ])
    );

    $school = School::where('display_name', 'North Ridge Academy')->first();

    expect($school)->not->toBeNull()
        ->and($school->is_private_student)->toBeFalse()
        ->and($school->school_type)->toBe('Brick Mortar');

    $profile = StudentProfile::where('school_id', $school->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->id_number)->toBe('STU-100');
});

it('requires a student id when creating a normal school (box unchecked)', function () {
    $admin = convertAdmin();
    $lead = Lead::factory()->create(['school_id' => null]);

    $response = $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload([
            'family_full_name' => 'North Ridge Academy',
            'family_name' => 'North Ridge Academy',
            'family_school_type' => 'Brick Mortar',
            'family_state' => 'MA',
            'family_timezone' => 'America/New_York',
        ])
    );

    $response->assertSessionHasErrors('id_number');
    expect(StudentProfile::count())->toBe(0);
});

it('errors when creating a family but the family name is blank', function () {
    $admin = convertAdmin();
    $lead = Lead::factory()->create(['school_id' => null]);

    $response = $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload([
            'create_private_family' => '1',
            'family_full_name' => 'Rivera Family',
            'family_state' => 'MA',
            'family_timezone' => 'America/New_York',
            'family_school_type' => 'Virtual',
        ])
    );

    $response->assertSessionHasErrors('family_name');
    expect(School::where('state', 'MA')->count())->toBe(0);
});

it('errors when the new family name already exists', function () {
    $admin = convertAdmin();
    School::factory()->create(['display_name' => 'Rivera Family']);
    $lead = Lead::factory()->create(['school_id' => null]);

    $response = $this->actingAs($admin)->post(
        route('admin.leads.convert.store', $lead),
        basePayload([
            'create_private_family' => '1',
            'family_full_name' => 'Rivera Family',
            'family_name' => 'Rivera Family',
            'family_school_type' => 'Virtual',
            'family_state' => 'MA',
            'family_timezone' => 'America/New_York',
        ])
    );

    $response->assertSessionHasErrors('family_name');
    expect(School::where('display_name', 'Rivera Family')->count())->toBe(1)
        ->and(StudentProfile::count())->toBe(0);
});
