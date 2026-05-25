<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->school = School::factory()->create();
    $this->student = User::factory()->create([
        'role' => Role::STUDENT->value,
        'status' => UserStatus::ACTIVE->value,
    ]);

    StudentProfile::factory()->create([
        'user_id' => $this->student->id,
        'school_id' => $this->school->id,
        'first_name' => 'Existing',
        'last_name' => 'Student',
    ]);
});

it('persists the second parent/guardian when creating a student', function () {
    Mail::fake();

    $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'first_name' => 'Ava',
        'last_name' => 'Smith',
        'username' => 'ava.smith.sp',
        'email' => 'ava.sp@example.com',
        'date_of_birth' => '2012-03-05',
        'school_id' => $this->school->id,
        'id_number' => 'STU-SP-1',
        'timezone' => 'America/New_York',
        'parent_guardian_name' => 'Mary Smith',
        'parent_guardian_email' => 'mary@example.com',
        'parent_guardian_phone' => '123-456-7890',
        'parent_guardian_2_name' => 'John Smith',
        'parent_guardian_2_email' => 'john@example.com',
        'parent_guardian_2_phone' => '987-654-3210',
    ]);

    $response->assertRedirect(route('admin.students.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('student_profiles', [
        'parent_guardian_2_name' => 'John Smith',
        'parent_guardian_2_email' => 'john@example.com',
        'parent_guardian_2_phone' => '987-654-3210',
    ]);
});

it('persists the second parent/guardian when updating a student', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.students.update', $this->student), [
        'first_name' => 'Existing',
        'last_name' => 'Student',
        'username' => 'existing.student.sp',
        'email' => 'existing.sp@example.com',
        'school_id' => $this->school->id,
        'id_number' => 'ID-SP-2',
        'timezone' => 'America/Chicago',
        'parent_guardian_2_name' => 'Second Parent',
        'parent_guardian_2_email' => 'second@example.com',
        'parent_guardian_2_phone' => '111-222-3333',
    ]);

    $response->assertRedirect(route('admin.students.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('student_profiles', [
        'user_id' => $this->student->id,
        'parent_guardian_2_name' => 'Second Parent',
        'parent_guardian_2_email' => 'second@example.com',
        'parent_guardian_2_phone' => '111-222-3333',
    ]);
});

it('rejects an invalid second parent email', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'username' => 'test.sp.email',
        'email' => 'test@example.com',
        'timezone' => 'America/Chicago',
        'parent_guardian_2_email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['parent_guardian_2_email']);
});

it('rejects an invalid second parent phone format', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'username' => 'test.sp.phone',
        'email' => 'test@example.com',
        'timezone' => 'America/Chicago',
        'parent_guardian_2_phone' => '123-456-7890abc',
    ]);

    $response->assertSessionHasErrors(['parent_guardian_2_phone']);
});

it('accepts a second parent phone with digits and dashes', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'username' => 'test.sp.phone.valid',
        'email' => 'test@example.com',
        'timezone' => 'America/Chicago',
        'parent_guardian_2_phone' => '123-456-7890',
    ]);

    $response->assertSessionDoesntHaveErrors(['parent_guardian_2_phone']);
});
