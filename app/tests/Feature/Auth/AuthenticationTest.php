<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using username', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('student can log in with non-email username', function () {
    $user = User::factory()->student()->create([
        'username' => 'john.smith.12345',
    ]);

    $response = $this->post('/login', [
        'username' => 'john.smith.12345',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('two students can share the same email with different usernames', function () {
    $user1 = User::factory()->student()->create([
        'username' => 'sibling1.smith',
        'email' => 'parent@example.com',
    ]);

    $user2 = User::factory()->student()->create([
        'username' => 'sibling2.smith',
        'email' => 'parent@example.com',
    ]);

    expect($user1->email)->toBe($user2->email);
    expect($user1->username)->not->toBe($user2->username);

    // Both can log in with their own username
    $this->post('/login', [
        'username' => 'sibling1.smith',
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $this->assertAuthenticatedAs($user1);
});

test('username uniqueness is enforced', function () {
    User::factory()->create(['username' => 'taken.username']);

    expect(fn () => User::factory()->create(['username' => 'taken.username']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('therapist can log in with email as username', function () {
    $user = User::factory()->therapist()->create([
        'username' => 'therapist@example.com',
        'email' => 'therapist@example.com',
    ]);

    $response = $this->post('/login', [
        'username' => 'therapist@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
