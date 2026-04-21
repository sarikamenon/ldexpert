<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        if ($category->isProtected()) {
            return false;
        }

        return $user->isAdmin();
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

        return $user->isAdmin();
    }
}
