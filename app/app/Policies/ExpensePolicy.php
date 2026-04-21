<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can view the expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can create expenses.
     */
    public function create(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can update the expense.
     *
     * Expenses auto-created from another module (e.g. a therapist bill payment)
     * are owned by that source and cannot be edited here.
     */
    public function update(User $user, Expense $expense): bool
    {
        if ($expense->source_type !== null) {
            return false;
        }

        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can delete the expense.
     *
     * Expenses auto-created from another module are removed only by deleting
     * the source record; admins cannot delete them directly.
     */
    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->source_type !== null) {
            return false;
        }

        return $user->role === Role::ADMIN;
    }
}
