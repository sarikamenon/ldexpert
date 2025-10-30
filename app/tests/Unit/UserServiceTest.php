<?php

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Services\UserService;
use App\DTOs\CreateUserDTO;
use App\Infrastructure\Repositories\EloquentUserRepository;

it('creates a user with a specific role', function () {
    app()->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    /** @var UserService $service */
    $service = app(UserService::class);

    $dto = CreateUserDTO::fromArray([
        'name' => 'Unit User',
        'email' => 'unit@example.com',
        'password' => 'Secret123!',
    ]);

    $user = $service->createWithRole($dto, 'therapist');

    expect($user->exists)->toBeTrue();
    expect($user->role)->toBe('therapist');
    $this->assertDatabaseHas('users', [
        'email' => 'unit@example.com',
        'role' => 'therapist',
    ]);
});
