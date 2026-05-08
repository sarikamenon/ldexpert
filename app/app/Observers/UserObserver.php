<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use RuntimeException;

class UserObserver
{
    /**
     * Prevent soft-deleting the Business ledger account (admin User#1).
     * All operating expense ledger entries are polymorphically linked to this user;
     * removing it would orphan those entries.
     */
    public function deleting(User $user): void
    {
        $this->guardBusinessAccount($user);
    }

    /**
     * Prevent force-deleting the Business ledger account.
     */
    public function forceDeleting(User $user): void
    {
        $this->guardBusinessAccount($user);
    }

    private function guardBusinessAccount(User $user): void
    {
        $businessId = (int) config('finance.business_account_user_id', 1);

        if ($user->id === $businessId) {
            throw new RuntimeException(
                "User #{$businessId} is the Business ledger account and cannot be deleted. ".
                'Operating expense ledger entries depend on this user.'
            );
        }
    }
}
