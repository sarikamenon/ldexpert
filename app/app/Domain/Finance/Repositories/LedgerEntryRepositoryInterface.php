<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\DTOs\AllTransactionsFilterDTO;
use App\DTOs\DataTablesParamsDTO;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

interface LedgerEntryRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, LedgerEntry>}
     */
    public function listForDataTables(string $ledgerableType, int $ledgerableId, DataTablesParamsDTO $params): array;

    public function getLastEntryForSchool(int $schoolId): ?LedgerEntry;

    public function getLastEntryForTherapist(int $therapistId): ?LedgerEntry;

    /**
     * @return array{
     *     total_invoiced: float,
     *     total_paid: float,
     *     outstanding: float,
     *     invoice_count: int,
     *     payment_count: int,
     *     total_credit_notes: float,
     *     credit_note_count: int,
     *     total_refunds: float,
     *     refund_count: int,
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
     *     total_credit_notes: float,
     *     credit_note_count: int,
     *     total_refunds: float,
     *     refund_count: int,
     *     current_balance: float,
     *     transaction_count: int
     * }
     */
    public function getTherapistStats(int $therapistId): array;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, LedgerEntry>}
     */
    public function listAllForDataTables(AllTransactionsFilterDTO $filters, DataTablesParamsDTO $params): array;
}
