<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ServiceSupportAgreement;
use App\Models\User;

final class ServiceSupportAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->isAdmin($user);
    }

    public function changeStatus(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->isAdmin($user);
    }

    public function assignTherapist(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->isAdmin($user);
    }

    public function unassignTherapist(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->isAdmin($user);
    }

    public function export(User $user, ServiceSupportAgreement $ssa): bool
    {
        return $this->viewAny($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }
}
