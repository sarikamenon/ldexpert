<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\DTOs\CreateUserDTO;
use App\Models\User;

interface UserRepositoryInterface
{
    public function create(CreateUserDTO $dto, string $role): User;

    public function findByEmail(string $email): ?User;
}
