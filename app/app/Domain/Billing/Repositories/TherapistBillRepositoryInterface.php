<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\DTOs\TherapistBillFilterDTO;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TherapistBillRepositoryInterface
{
    public function create(array $data): TherapistBill;

    public function find(int $id): ?TherapistBill;

    public function list(TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
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
     * @return Collection<SessionLog>
     */
    public function getAvailableSessionLogsForBillingCreation(array $filters): Collection;

    /**
     * Get unique therapist IDs from available session logs
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int>
     */
    public function getAvailableTherapistIdsForBillingCreation(array $filters): Collection;

    /**
     * Get bills by therapist with filters
     */
    public function getBillsByTherapist(int $therapistId, TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;
}
