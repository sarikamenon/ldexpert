<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use Illuminate\Support\Carbon;

/**
 * Resolves the initial billing_start_date for a newly-created billing schedule.
 *
 * The therapist anchor (1st / 16th) assumes the seeded frequency is semi_monthly
 * (the current default) and is intentionally NOT frequency-aware — if an admin
 * changes the frequency they must adjust billing_start_date themselves.
 */
final class BillingStartDateResolver
{
    /**
     * Therapist bills: created on/before the 15th anchor to the 1st of the
     * current month; created on/after the 16th anchor to the 16th.
     */
    public function forTherapist(Carbon $createdAt): Carbon
    {
        $reference = $createdAt->copy();

        return $reference->day <= 15
            ? $reference->copy()->startOfMonth()->startOfDay()
            : $reference->copy()->setDay(16)->startOfDay();
    }

    /**
     * School/family invoices: private-student schools (prepaid/advance) start
     * the 1st of NEXT month — the first month's invoice is created manually.
     * Non-private schools (postpaid/standard) start the 1st of the current month.
     */
    public function forSchool(bool $isPrivate, Carbon $createdAt): Carbon
    {
        $reference = $createdAt->copy();

        return $isPrivate
            ? $reference->copy()->addMonthNoOverflow()->startOfMonth()->startOfDay()
            : $reference->copy()->startOfMonth()->startOfDay();
    }
}
