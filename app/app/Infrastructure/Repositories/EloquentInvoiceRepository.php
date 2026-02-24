<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\InvoiceFilterDTO;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Models\Invoice;
use App\Models\SessionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        $invoice->refresh();

        return $invoice;
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::with(['sessionLogs.student', 'sessionLogs.service', 'sessionLogs.therapist', 'school', 'sentBy'])
            ->find($id);
    }

    public function list(InvoiceFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->applyFilters(Invoice::query(), $filters);

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, Invoice>}
     */
    public function listForDataTables(InvoiceFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = $this->applyFilters(
            Invoice::query(),
            $filters,
        );

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('invoices.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('school_display_name', 'like', '%'.$search.'%');
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('invoices.id');

        $orderColumn = $params->orderColumn ?? 'invoice_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';

        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, Invoice> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    private function applyFilters($query, InvoiceFilterDTO $filters)
    {
        if ($filters->schoolId !== null) {
            $query->where('school_id', $filters->schoolId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->dateFrom !== null) {
            $query->whereDate('billing_period_start', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('billing_period_end', '<=', $filters->dateTo);
        }

        if ($filters->invoiceNumber !== null) {
            $query->where('invoice_number', 'like', '%'.$filters->invoiceNumber.'%');
        }

        return $query;
    }

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
     */
    public function getApprovedSessionLogsForInvoice(array $sessionLogIds): Collection
    {
        return SessionLog::query()
            ->whereIn('id', $sessionLogIds)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->with(['student', 'service', 'therapist', 'school'])
            ->get();
    }

    /**
     * @param  array<int>  $sessionLogIds
     */
    public function linkSessionLogs(Invoice $invoice, array $sessionLogIds): void
    {
        SessionLog::whereIn('id', $sessionLogIds)
            ->update(['invoice_id' => $invoice->id]);
    }

    public function markAsSent(Invoice $invoice, int $sentById): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::SENT->value,
            'sent_at' => now(),
            'sent_by_id' => $sentById,
        ]);

        return $invoice->refresh();
    }

    public function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastInvoice = Invoice::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice && preg_match('/INV-(\d{8})-(\d{3})/', $lastInvoice->invoice_number, $matches)) {
            $sequence = (int) $matches[2] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('INV-%s-%03d', $date, $sequence);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<SessionLog>
     */
    public function getAvailableSessionLogsForInvoiceCreation(array $filters): Collection
    {
        $query = SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->with(['student', 'service', 'therapist', 'school']);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('session_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['school_id']) && $filters['school_id']) {
            $query->where('school_id', $filters['school_id']);
        }

        if (isset($filters['therapist_id']) && $filters['therapist_id']) {
            $query->where('therapist_id', $filters['therapist_id']);
        }

        if (isset($filters['student_id']) && $filters['student_id']) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['service_id']) && $filters['service_id']) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('service', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('school', function ($subQ) use ($search) {
                        $subQ->where('display_name', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('session_date', 'desc')->get();
    }

    public function getAvailableServiceIdsForSchool(int $schoolId): Collection
    {
        return SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->where('school_id', $schoolId)
            ->distinct()
            ->pluck('service_id');
    }

    public function getAvailableSchoolIdsForInvoiceCreation(array $filters): Collection
    {
        $query = SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('session_date', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->distinct()->pluck('school_id');
    }

    public function updateTotals(Invoice $invoice, float $subtotal, float $taxTotal, float $total): Invoice
    {
        $invoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        return $invoice->refresh();
    }

    public function unlinkAllSessionsForInvoice(Invoice $invoice): void
    {
        SessionLog::where('invoice_id', $invoice->id)
            ->update(['invoice_id' => null]);
    }

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
     */
    public function getSessionLogsForInvoiceUpdate(Invoice $invoice, array $sessionLogIds): Collection
    {
        if (empty($sessionLogIds)) {
            return collect();
        }

        return SessionLog::query()
            ->whereIn('id', $sessionLogIds)
            ->where('school_id', $invoice->school_id)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_school', true)
            ->where(function ($q) use ($invoice) {
                $q->whereNull('invoice_id')
                    ->orWhere('invoice_id', $invoice->id);
            })
            ->with(['student', 'service', 'therapist', 'school'])
            ->get();
    }
}
