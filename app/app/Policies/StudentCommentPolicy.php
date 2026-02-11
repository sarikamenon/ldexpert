<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\SSA\Services\SSAService;
use App\Enums\Role;
use App\Models\StudentComment;
use App\Models\User;

class StudentCommentPolicy
{
    public function __construct(
        private readonly SSAService $ssaService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isTherapist($user);
    }

    public function view(User $user, ?StudentComment $studentComment = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isTherapist($user) && $studentComment) {
            return $this->ssaService->hasStudentAssignedToTherapist(
                $studentComment->student_id,
                $user->id
            );
        }

        return false;
    }

    public function create(User $user, ?User $student = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isTherapist($user) && $student) {
            return $this->ssaService->hasStudentAssignedToTherapist(
                $student->id,
                $user->id
            );
        }

        return false;
    }

    private function isAdmin(User $user): bool
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return $role === Role::ADMIN;
    }

    private function isTherapist(User $user): bool
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return $role === Role::THERAPIST;
    }
}
