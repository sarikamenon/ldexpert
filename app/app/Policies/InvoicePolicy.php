<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN && $invoice->isDraft();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN && $invoice->isDraft();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN && ($invoice->isDraft() || $invoice->isSent());
    }

    public function resendEmail(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN && $invoice->isSent() && ! $invoice->isPaid();
    }
}
