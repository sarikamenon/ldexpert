<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistBillFilterDTO;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TherapistBillRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): TherapistBill;

    public function find(int $id): ?TherapistBill;

    /** @return LengthAwarePaginator<int, TherapistBill> */
    public function list(TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistBill>}
     */
    public function listForDataTables(TherapistBillFilterDTO $filters, DataTablesParamsDTO $params): array;

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<int, SessionLog>
     */
    public function getApprovedSessionLogsForBilling(array $sessionLogIds): Collection;

    /**
     * @param  array<int>  $sessionLogIds
     */
    public function linkSessionLogs(TherapistBill $bill, array $sessionLogIds): void;

    public function markAsSent(TherapistBill $bill, int $sentById): TherapistBill;

    public function generateBillNumber(): string;

    /**
     * Get available session logs for bill creation with filters
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SessionLog>
     */
    public function getAvailableSessionLogsForBillingCreation(array $filters): Collection;

    public function unlinkAllSessionsForTherapistBill(TherapistBill $bill): void;

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<int, SessionLog>
     */
    public function getSessionLogsForTherapistBillUpdate(TherapistBill $bill, array $sessionLogIds): Collection;

    public function updateTotals(TherapistBill $bill, float $subtotal, float $adjustmentsTotal, float $totalDue): TherapistBill;

    /**
     * Get unique therapist IDs from available session logs
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, int>
     */
    public function getAvailableTherapistIdsForBillingCreation(array $filters): Collection;

    /**
     * Get bills by therapist with filters
     *
     * @return LengthAwarePaginator<int, TherapistBill>
     */
    public function getBillsByTherapist(int $therapistId, TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    public function delete(TherapistBill $bill): void;
}
