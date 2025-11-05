<?php

use App\Models\User;

it('therapist can see students index', function () {
    $therapist = User::factory()->therapist()->create();
    $this->actingAs($therapist);

    $this->get('/therapist/students')->assertOk();
});

it('student cannot see therapist students index', function () {
    $student = User::factory()->student()->create();
    $this->actingAs($student);

    $this->get('/therapist/students')->assertForbidden();
});
