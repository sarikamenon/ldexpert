<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        if ($category->isProtected()) {
            return false;
        }

        return $user->role === Role::ADMIN;
    }

    /**
     * Whether the user can flip the active flag. Protected categories
     * must always remain active.
     */
    public function toggleStatus(User $user, ExpenseCategory $category): bool
    {
        if ($category->isProtected()) {
            return false;
        }

        return $user->role === Role::ADMIN;
    }
}
