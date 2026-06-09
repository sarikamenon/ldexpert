<?php

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user with a specific role', function () {
    /** @var UserService $service */
    $service = app(UserService::class);

    $dto = CreateUserDTO::fromArray([
        'name' => 'Unit User',
        'email' => 'unit@example.com',
        'password' => 'Secret123!',
    ]);

    $user = $service->createWithRole($dto, 'therapist');

    expect($user->exists)->toBeTrue();
    expect((string) ($user->role instanceof \App\Enums\Role ? $user->role->value : $user->role))->toBe('therapist');
    $this->assertDatabaseHas('users', [
        'email' => 'unit@example.com',
        'role' => 'therapist',
    ]);
});

it('finds a user by id', function () {
    /** @var UserService $service */
    $service = app(UserService::class);

    $user = User::factory()->create();

    expect($service->findById($user->id)?->id)->toBe($user->id);
});

it('returns null when finding a user id that does not exist', function () {
    /** @var UserService $service */
    $service = app(UserService::class);

    expect($service->findById(999999))->toBeNull();
});
