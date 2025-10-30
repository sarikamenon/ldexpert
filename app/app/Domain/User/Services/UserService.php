<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateUserDTO;
use App\Models\User;

class UserService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function createWithRole(CreateUserDTO $dto, string $role): User
    {
        return $this->users->create($dto, $role);
    }
}
