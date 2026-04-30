<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\User;

final class LedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    public function view(User $user, LedgerEntry $entry): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Only admins can record adjustments. The transaction-type whitelist is
     * enforced by the create form/request, not by the policy.
     */
    public function createAdjustment(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Only credit_note and refund rows are mutable from the ledger UI.
     * Source-document rows (invoices, bills, payments, expenses) must be
     * edited via their owning page so their parent record stays in sync.
     */
    public function update(User $user, LedgerEntry $entry): bool
    {
        return $user->role === Role::ADMIN && $this->isAdjustment($entry);
    }

    public function delete(User $user, LedgerEntry $entry): bool
    {
        return $user->role === Role::ADMIN && $this->isAdjustment($entry);
    }

    private function isAdjustment(LedgerEntry $entry): bool
    {
        return $entry->transaction_type === TransactionType::CREDIT_NOTE
            || $entry->transaction_type === TransactionType::REFUND;
    }
}
