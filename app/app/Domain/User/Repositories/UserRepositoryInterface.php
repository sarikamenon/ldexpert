<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\DTOs\CreateUserDTO;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(CreateUserDTO $dto, string $role): User;

    public function findByEmail(string $email): ?User;

    public function countStudentsByStatus(string $status): int;

    public function countNewStudentsThisMonth(): int;

    public function listByRole(string $role): Collection;
}
