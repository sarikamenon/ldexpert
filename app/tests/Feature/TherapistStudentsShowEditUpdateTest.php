<?php

use App\Models\User;

it('therapist can view, edit and update a student', function () {
    $therapist = User::factory()->therapist()->create();
    $student = User::factory()->student()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    // assign pivot
    $therapist->students()->attach($student->id, [
        'assigned_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($therapist);

    // show
    $this->get("/therapist/students/{$student->id}")->assertOk();

    // edit
    $this->get("/therapist/students/{$student->id}/edit")->assertOk();

    // update
    $this->patch("/therapist/students/{$student->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'first_name' => 'New',
        'last_name' => 'Name',
        'password' => '',
        'date_of_birth' => '2010-01-01',
        'grade_level' => '5',
        'parent_id' => null,
    ])->assertRedirect();

    expect($student->fresh()->name)->toBe('New Name');
    expect($student->fresh()->email)->toBe('new@example.com');
    expect($student->fresh()->studentProfile->first_name)->toBe('New');
    expect($student->fresh()->studentProfile->last_name)->toBe('Name');
});

it('student cannot edit another student', function () {
    $student = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    $this->actingAs($student);

    $this->get("/therapist/students/{$other->id}/edit")->assertForbidden();
});
