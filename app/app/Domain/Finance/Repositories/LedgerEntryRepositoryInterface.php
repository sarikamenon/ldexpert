<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\LedgerEntry;

interface LedgerEntryRepositoryInterface
{
    public function getLastEntryForSchool(int $schoolId): ?LedgerEntry;

    public function getLastEntryForTherapist(int $therapistId): ?LedgerEntry;

    /**
     * @return array{
     *     total_invoiced: float,
     *     total_paid: float,
     *     outstanding: float,
     *     invoice_count: int,
     *     payment_count: int,
     *     current_balance: float,
     *     transaction_count: int
     * }
     */
    public function getSchoolStats(int $schoolId): array;

    /**
     * @return array{
     *     total_billed: float,
     *     total_paid: float,
     *     outstanding: float,
     *     bill_count: int,
     *     payment_count: int,
     *     current_balance: float,
     *     transaction_count: int
     * }
     */
    public function getTherapistStats(int $therapistId): array;
}

