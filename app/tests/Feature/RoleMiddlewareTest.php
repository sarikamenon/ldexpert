<?php

use App\Models\User;

it('allows therapist to access therapist routes', function () {
    $user = User::factory()->create(['role' => 'therapist']);
    $this->actingAs($user);
    $this->get('/therapist/dashboard')->assertOk();
});

it('forbids student from therapist routes', function () {
    $user = User::factory()->create(['role' => 'student']);
    $this->actingAs($user);
    $this->get('/therapist/dashboard')->assertForbidden();
});
