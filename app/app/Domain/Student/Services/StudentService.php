<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Mail\WelcomeStudentMail;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class StudentService
{
    public function __construct(
        private readonly StudentRepositoryInterface $repository,
    ) {}

    public function create(CreateStudentDTO $dto): StudentProfile
    {
        $userData = $dto->toUserArray();
        $userData['password'] = Hash::make($dto->password);

        $profile = $this->repository->create(
            $userData,
            $dto->toProfileArray(0) // user_id will be set in repository
        );

        // Send welcome email to student's user email
        Mail::to($dto->email)->send(
            new WelcomeStudentMail(
                name: $dto->firstName.' '.$dto->lastName,
                email: $dto->email,
                plainPassword: $dto->password
            )
        );

        return $profile;
    }

    public function update(User $user, UpdateStudentDTO $dto): StudentProfile
    {
        return $this->repository->update(
            $user,
            $dto->toUserArray(),
            $dto->toProfileArray()
        );
    }

    public function changeStatus(User $user, ChangeStudentStatusDTO $dto): User
    {
        return $this->repository->changeStatus($user, $dto);
    }

    public function list(StudentFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function getMetrics(?string $status = null): array
    {
        return $this->repository->getMetrics($status);
    }

    public function export(StudentFilterDTO $filters): Collection
    {
        return $this->repository->export($filters);
    }

    public function find(int $id): ?StudentProfile
    {
        return $this->repository->find($id);
    }
}
