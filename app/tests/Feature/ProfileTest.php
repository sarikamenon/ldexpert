<?php

use App\Enums\Role;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('username is synced to the new email for non-student users when email changes', function () {
    $user = User::factory()->therapist()->create([
        'email' => 'old@example.com',
        'username' => 'old@example.com',
    ]);

    $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->email)->toBe('new@example.com');
    expect($user->username)->toBe('new@example.com');
});

test('username is not changed for student users when email changes', function () {
    $user = User::factory()->create([
        'role' => Role::STUDENT->value,
        'email' => 'old.student@example.com',
        'username' => 'jane.doe.123',
    ]);

    $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => 'new.student@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->email)->toBe('new.student@example.com');
    expect($user->username)->toBe('jane.doe.123');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});
