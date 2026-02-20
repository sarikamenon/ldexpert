<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\InvoicePaymentFilterDTO;
use App\DTOs\TherapistBillPaymentFilterDTO;
use App\Models\InvoicePayment;
use App\Models\TherapistBillPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PaymentsListService
{
    public function getInvoicePayments(InvoicePaymentFilterDTO $filters): array
    {
        $query = InvoicePayment::query()
            ->with(['school', 'invoice', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $search = $filters->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice.school', function (Builder $sq) use ($search) {
                        $sq->where('display_name', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function (Builder $sq) use ($search) {
                        $sq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        /** @var LengthAwarePaginator $payments */
        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = InvoicePayment::query()
            ->when($filters->fromDate, fn (Builder $q) => $q->where('paid_at', '>=', $filters->fromDate))
            ->when($filters->toDate, fn (Builder $q) => $q->where('paid_at', '<=', $filters->toDate))
            ->when($filters->method, fn (Builder $q) => $q->where('method', $filters->method))
            ->sum('amount');

        return [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
        ];
    }

    public function getTherapistBillPayments(TherapistBillPaymentFilterDTO $filters): array
    {
        $query = TherapistBillPayment::query()
            ->with(['therapist', 'therapistBill', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        $this->applyCommonFilters($query, $filters->fromDate, $filters->toDate, $filters->method);

        if ($filters->search) {
            $search = $filters->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('therapistBill.therapist', function (Builder $sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('therapistBill', function (Builder $sq) use ($search) {
                        $sq->where('bill_number', 'like', "%{$search}%");
                    });
            });
        }

        /** @var LengthAwarePaginator $payments */
        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = TherapistBillPayment::query()
            ->when($filters->fromDate, fn (Builder $q) => $q->where('paid_at', '>=', $filters->fromDate))
            ->when($filters->toDate, fn (Builder $q) => $q->where('paid_at', '<=', $filters->toDate))
            ->when($filters->method, fn (Builder $q) => $q->where('method', $filters->method))
            ->sum('amount');

        return [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
        ];
    }

    private function applyCommonFilters(Builder $query, ?string $fromDate, ?string $toDate, ?string $method): void
    {
        if ($fromDate) {
            $query->where('paid_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('paid_at', '<=', $toDate);
        }

        if ($method) {
            $query->where('method', $method);
        }
    }
}
