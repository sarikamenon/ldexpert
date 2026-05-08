<?php

declare(strict_types=1);

namespace App\Domain\Finance\Support;

use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\User;

final class LedgerAccountPresenter
{
    /**
     * Human-readable display name for the account that owns a ledger entry.
     * The Business account (User#business_account_user_id) renders as "Operating Expenses"
     * rather than the admin user's personal name.
     */
    public static function displayName(LedgerEntry $entry): string
    {
        $businessId = (int) config('finance.business_account_user_id', 1);

        if ($entry->ledgerable_type === User::class && (int) $entry->ledgerable_id === $businessId) {
            return 'Operating Expenses';
        }

        $ledgerable = $entry->ledgerable;

        if ($ledgerable instanceof School) {
            return $ledgerable->display_name ?? $ledgerable->full_name ?? 'Unknown School';
        }

        if ($ledgerable instanceof User) {
            return $ledgerable->name ?? 'Unknown User';
        }

        return 'Unknown';
    }

    /**
     * Short account-type label: "School", "Therapist", or "Business".
     */
    public static function accountType(LedgerEntry $entry): string
    {
        $businessId = (int) config('finance.business_account_user_id', 1);

        if ($entry->ledgerable_type === School::class) {
            return 'School';
        }

        if ($entry->ledgerable_type === User::class && (int) $entry->ledgerable_id === $businessId) {
            return 'Business';
        }

        return 'Therapist';
    }
}
