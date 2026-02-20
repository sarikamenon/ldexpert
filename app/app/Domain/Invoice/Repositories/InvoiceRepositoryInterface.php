<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Repositories;

use App\DTOs\InvoiceFilterDTO;
use App\Models\Invoice;
use App\Models\SessionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InvoiceRepositoryInterface
{
    public function create(array $data): Invoice;

    public function update(Invoice $invoice, array $data): Invoice;

    public function find(int $id): ?Invoice;

    public function list(InvoiceFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
     */
    public function getApprovedSessionLogsForInvoice(array $sessionLogIds): Collection;

    /**
     * @param  array<int>  $sessionLogIds
     */
    public function linkSessionLogs(Invoice $invoice, array $sessionLogIds): void;

    public function markAsSent(Invoice $invoice, int $sentById): Invoice;

    public function generateInvoiceNumber(): string;

    /**
     * Get available session logs for invoice creation with filters
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<SessionLog>
     */
    public function getAvailableSessionLogsForInvoiceCreation(array $filters): Collection;

    /**
     * Get unique service IDs for a school from available session logs
     *
     * @return Collection<int>
     */
    public function getAvailableServiceIdsForSchool(int $schoolId): Collection;

    /**
     * Get unique school IDs from available session logs
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int>
     */
    public function getAvailableSchoolIdsForInvoiceCreation(array $filters): Collection;

    public function updateTotals(Invoice $invoice, float $subtotal, float $taxTotal, float $total): Invoice;

    public function unlinkAllSessionsForInvoice(Invoice $invoice): void;

    /**
     * Get session logs for attach/update: approved, billable, same school, and either uninvoiced or already on this invoice.
     *
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
     */
    public function getSessionLogsForInvoiceUpdate(Invoice $invoice, array $sessionLogIds): Collection;
}
