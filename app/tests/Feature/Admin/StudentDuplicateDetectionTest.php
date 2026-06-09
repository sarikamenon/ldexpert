<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->admin()->create();
}

function existingStudent(School $school, array $profile = [], array $user = []): StudentProfile
{
    $owner = User::factory()->student()->create($user);

    return StudentProfile::factory()->create(array_merge([
        'user_id' => $owner->id,
        'school_id' => $school->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ], $profile));
}

function newStudentPayload(School $school, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'username' => 'jane.smith.new',
        'email' => 'new@example.com',
        'gender' => 'Female',
        'date_of_birth' => '2013-01-01',
        'school_id' => $school->id,
        'id_number' => 'STU-NEW',
        'timezone' => 'America/New_York',
        'grade_level' => '5',
    ], $overrides);
}

it('redirects back with matches and does not create when a name duplicate exists', function () {
    $school = School::factory()->create();
    existingStudent($school);

    $response = $this->actingAs(admin())
        ->post(route('admin.students.store'), newStudentPayload($school));

    $response->assertRedirect()->assertSessionHas('duplicateMatches');
    expect(StudentProfile::where('first_name', 'Jane')->where('last_name', 'Smith')->count())->toBe(1);
});

it('creates the student when the duplicate is acknowledged', function () {
    $school = School::factory()->create();
    existingStudent($school);

    $response = $this->actingAs(admin())->post(
        route('admin.students.store'),
        newStudentPayload($school, ['duplicate_acknowledged' => '1']),
    );

    $response->assertRedirect(route('admin.students.index'))->assertSessionHas('status');
    expect(StudentProfile::where('first_name', 'Jane')->where('last_name', 'Smith')->count())->toBe(2);
});

it('creates the student normally when there is no name match', function () {
    $school = School::factory()->create();
    existingStudent($school, ['first_name' => 'Bob']);

    $response = $this->actingAs(admin())
        ->post(route('admin.students.store'), newStudentPayload($school));

    $response->assertRedirect(route('admin.students.index'))->assertSessionHas('status');
    $this->assertDatabaseHas('student_profiles', ['first_name' => 'Jane', 'last_name' => 'Smith']);
});

it('does not flag a sibling sharing the parent email but with a different name', function () {
    $school = School::factory()->create();
    existingStudent($school, ['first_name' => 'Bob'], ['email' => 'parent@example.com']);

    $response = $this->actingAs(admin())->post(
        route('admin.students.store'),
        newStudentPayload($school, ['email' => 'parent@example.com']),
    );

    $response->assertRedirect(route('admin.students.index'))->assertSessionHas('status');
});

it('does not flag a student against itself when editing', function () {
    $school = School::factory()->create();
    $profile = existingStudent($school);
    $student = $profile->user;

    $response = $this->actingAs(admin())->put(route('admin.students.update', $student), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'username' => $student->username,
        'email' => $student->email,
        'gender' => 'Female',
        'date_of_birth' => '2013-01-01',
        'school_id' => $school->id,
        'id_number' => $profile->id_number ?? 'STU-EDIT',
        'timezone' => 'America/New_York',
        'grade_level' => '6',
    ]);

    $response->assertRedirect(route('admin.students.index'))->assertSessionHas('status');
});
