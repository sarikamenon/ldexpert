<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\InvoicePaymentFilterDTO;
use App\DTOs\TherapistBillPaymentFilterDTO;
use App\Models\InvoicePayment;
use App\Models\TherapistBillPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PaymentsListService
{
    /**
     * @return array<string, mixed>
     */
    public function getInvoicePayments(InvoicePaymentFilterDTO $filters): array
    {
        $query = InvoicePayment::query()
            ->with(['school', 'invoice', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $this->applyInvoicePaymentSearch($query, $filters->search);
        }

        /** @var LengthAwarePaginator<int, InvoicePayment> $payments */
        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = $this->getInvoicePaymentsTotalAmount($filters);

        return [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
        ];
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, InvoicePayment>}
     */
    public function listInvoicePaymentsForDataTables(InvoicePaymentFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = InvoicePayment::query()
            ->with(['school', 'invoice', 'recordedBy']);

        $this->applyCommonFilters($baseQuery, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $this->applyInvoicePaymentSearch($baseQuery, $filters->search);
        }

        $queryForTotal = (clone $baseQuery);
        $recordsTotal = $queryForTotal->count('invoice_payments.id');

        if ($params->searchValue) {
            $this->applyInvoicePaymentSearch($baseQuery, $params->searchValue);
        }
        $recordsFiltered = (clone $baseQuery)->count('invoice_payments.id');

        $orderColumn = $params->orderColumn ?? 'paid_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, InvoicePayment> $rows */
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

    public function getInvoicePaymentsTotalAmount(InvoicePaymentFilterDTO $filters): float
    {
        $query = InvoicePayment::query();
        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);
        if ($filters->search) {
            $this->applyInvoicePaymentSearch($query, $filters->search);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  Builder<InvoicePayment>  $query
     */
    private function applyInvoicePaymentSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('invoice_payments.reference', 'like', "%{$search}%")
                ->orWhereHas('invoice.school', function (Builder $sq) use ($search) {
                    $sq->where('display_name', 'like', "%{$search}%") // @phpstan-ignore argument.type
                        ->orWhere('full_name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                })
                ->orWhereHas('invoice', function (Builder $sq) use ($search) {
                    $sq->where('invoice_number', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getTherapistBillPayments(TherapistBillPaymentFilterDTO $filters): array
    {
        $query = TherapistBillPayment::query()
            ->with(['therapist', 'therapistBill', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $this->applyTherapistBillPaymentSearch($query, $filters->search);
        }

        /** @var LengthAwarePaginator<int, TherapistBillPayment> $payments */
        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = $this->getTherapistBillPaymentsTotalAmount($filters);

        return [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
        ];
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistBillPayment>}
     */
    public function listTherapistBillPaymentsForDataTables(TherapistBillPaymentFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = TherapistBillPayment::query()
            ->with(['therapist', 'therapistBill', 'recordedBy']);

        $this->applyCommonFilters($baseQuery, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $this->applyTherapistBillPaymentSearch($baseQuery, $filters->search);
        }

        $recordsTotal = (clone $baseQuery)->count('therapist_bill_payments.id');

        if ($params->searchValue) {
            $this->applyTherapistBillPaymentSearch($baseQuery, $params->searchValue);
        }
        $recordsFiltered = (clone $baseQuery)->count('therapist_bill_payments.id');

        $orderColumn = $params->orderColumn ?? 'paid_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, TherapistBillPayment> $rows */
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

    public function getTherapistBillPaymentsTotalAmount(TherapistBillPaymentFilterDTO $filters): float
    {
        $query = TherapistBillPayment::query();
        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);
        if ($filters->search) {
            $this->applyTherapistBillPaymentSearch($query, $filters->search);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  Builder<TherapistBillPayment>  $query
     */
    private function applyTherapistBillPaymentSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('therapist_bill_payments.reference', 'like', "%{$search}%")
                ->orWhereHas('therapistBill.therapist', function (Builder $sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                })
                ->orWhereHas('therapistBill', function (Builder $sq) use ($search) {
                    $sq->where('bill_number', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                });
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyCommonFilters(Builder $query, ?string $fromDate, ?string $toDate, ?string $method): void
    {
        if ($fromDate) {
            $query->where('paid_at', '>=', $fromDate); // @phpstan-ignore argument.type
        }

        if ($toDate) {
            $query->where('paid_at', '<=', $toDate); // @phpstan-ignore argument.type
        }

        if ($method) {
            $query->where('method', $method); // @phpstan-ignore argument.type
        }
    }
}
