<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\DTOs\InvoiceFilterDTO;
use App\Enums\InvoiceStatus;
use App\Enums\SessionLogStatus;
use App\Models\Invoice;
use App\Models\SessionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $query = Invoice::query()
            ->with(['school', 'sessionLogs']);

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
            $query->where('invoice_number', 'like', '%' . $filters->invoiceNumber . '%');
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param array<int> $sessionLogIds
     * @return Collection<SessionLog>
     */
    public function getFinalizedSessionLogsForInvoice(array $sessionLogIds): Collection
    {
        return SessionLog::query()
            ->whereIn('id', $sessionLogIds)
            ->where('status', SessionLogStatus::FINALIZED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->with(['student', 'service', 'therapist', 'school'])
            ->get();
    }

    /**
     * @param array<int> $sessionLogIds
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
}
