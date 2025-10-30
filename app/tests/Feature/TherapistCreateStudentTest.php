<?php

use App\Models\User;

it('therapist can create a student', function () {
    $therapist = User::factory()->create(['role' => 'therapist']);
    $this->actingAs($therapist);

    $response = $this->post('/therapist/students', [
        'name' => 'Student A',
        'email' => 'student.a@example.com',
        'password' => 'Secret123!',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'email' => 'student.a@example.com',
        'role' => 'student',
    ]);
});
