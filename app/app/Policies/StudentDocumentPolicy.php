<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\SSA\Services\SSAService;
use App\Enums\Role;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use App\Models\User;

class StudentDocumentPolicy
{
    public function __construct(
        private readonly SSAService $ssaService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, StudentDocument $document): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isTherapist($user)) {
            // Therapist can view if document is for their session log
            if ($document->isForSessionLog()) {
                $sessionLog = $document->documentable;
                if ($sessionLog instanceof SessionLog) {
                    return $sessionLog->therapist_id === $user->id;
                }
            }

            // Therapist can view if document is for a student they're assigned to
            if ($document->isForStudent()) {
                $student = $document->documentable;
                if ($student instanceof User) {
                    return $this->ssaService->hasStudentAssignedToTherapist(
                        $student->id,
                        $user->id
                    );
                }
            }
        }

        return false;
    }

    public function create(User $user, $documentable = null): bool
    {
        if ($this->isAdmin($user)) {
            // Admin can create documents for students
            if ($documentable instanceof User && $documentable->role === Role::STUDENT) {
                return true;
            }
        }

        if ($this->isTherapist($user)) {
            // Therapist can create documents for their session logs
            if ($documentable instanceof SessionLog) {
                return $documentable->therapist_id === $user->id;
            }
            // Therapist can create documents for students they're assigned to (e.g. common documents)
            if ($documentable instanceof User && $documentable->role === Role::STUDENT) {
                return $this->ssaService->hasStudentAssignedToTherapist(
                    $documentable->id,
                    $user->id
                );
            }
        }

        return false;
    }

    public function delete(User $user, StudentDocument $document): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isTherapist($user)) {
            // Therapist can delete if they uploaded it
            return $document->uploaded_by_id === $user->id;
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
