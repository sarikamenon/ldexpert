<?php

use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;

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
