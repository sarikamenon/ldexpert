<?php

use App\DTOs\CreateUserDTO;
use App\Infrastructure\Repositories\EloquentUserRepository;

it('persists a user via repository', function () {
    $repo = app(EloquentUserRepository::class);

    $dto = CreateUserDTO::fromArray([
        'name' => 'Repo User',
        'email' => 'repo@example.com',
        'password' => 'Secret123!',
    ]);

    $user = $repo->create($dto, 'therapist');
    expect($user->id)->not->toBeNull();
    $this->assertDatabaseHas('users', [
        'email' => 'repo@example.com',
        'role' => 'therapist',
    ]);
});
