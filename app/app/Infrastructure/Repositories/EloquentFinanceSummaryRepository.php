<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentFinanceSummaryRepository implements FinanceSummaryRepositoryInterface
{
    public function getRevenueInvoiced(Carbon $start, Carbon $end): float
    {
        return (float) Invoice::where('status', '!=', InvoiceStatus::DRAFT)
            ->sum('total');
    }

    public function getRevenueCollected(Carbon $start, Carbon $end): float
    {
        return (float) InvoicePayment::whereNotNull('paid_at')
            ->sum('amount');
    }

    public function getTherapistCosts(Carbon $start, Carbon $end): float
    {
        return (float) TherapistBill::where('status', '!=', TherapistBillStatus::DRAFT)
            ->sum('total_due');
    }

    public function getTherapistPayments(Carbon $start, Carbon $end): float
    {
        return (float) TherapistBillPayment::whereNotNull('paid_at')
            ->sum('amount');
    }

    public function getOtherExpenses(Carbon $start, Carbon $end): float
    {
        return (float) Expense::sum('amount');
    }

    public function getOutstandingReceivablesTotals(InvoiceStatus $paidStatus): array
    {
        // NOTE: Payments are linked to invoices via the invoice_payment_allocations table,
        // not a direct invoice_id column on invoice_payments. We therefore aggregate the
        // allocated amounts per invoice using that pivot table and ignore soft-deleted
        // payments. Query builder is used here for efficiency across large aggregates.
        $outstandingReceivables = DB::table('invoices')
            ->select(
                DB::raw('SUM(total) as total_invoiced'),
                DB::raw('COALESCE(SUM(payments.amount_paid), 0) as total_paid')
            )
            ->leftJoin(
                DB::raw('(
                    SELECT ipa.invoice_id, SUM(ipa.allocated_amount) AS amount_paid
                    FROM invoice_payment_allocations ipa
                    INNER JOIN invoice_payments ip
                        ON ip.id = ipa.invoice_payment_id
                        AND ip.deleted_at IS NULL
                    GROUP BY ipa.invoice_id
                ) AS payments'),
                'invoices.id',
                '=',
                'payments.invoice_id'
            )
            ->where('invoices.status', '!=', $paidStatus->value)
            ->first();

        return [
            'total_invoiced' => (float) ($outstandingReceivables->total_invoiced ?? 0),
            'total_paid' => (float) ($outstandingReceivables->total_paid ?? 0),
        ];
    }

    public function getOverdueInvoicesCount(InvoiceStatus $paidStatus, Carbon $now): int
    {
        return Invoice::where('status', '!=', $paidStatus->value)
            ->where('due_date', '<', $now)
            ->count();
    }

    public function getOutstandingPayablesTotals(TherapistBillStatus $paidStatus): array
    {
        // Similar to invoices, therapist bill payments are linked via the
        // therapist_bill_payment_allocations table, not a direct therapist_bill_id
        // column on therapist_bill_payments. We aggregate allocated amounts per
        // bill via that pivot and ignore soft-deleted payments.
        $outstandingPayables = DB::table('therapist_bills')
            ->select(
                DB::raw('SUM(total_due) as total_billed'),
                DB::raw('COALESCE(SUM(payments.amount_paid), 0) as total_paid')
            )
            ->leftJoin(
                DB::raw('(
                    SELECT tbpa.therapist_bill_id, SUM(tbpa.allocated_amount) AS amount_paid
                    FROM therapist_bill_payment_allocations tbpa
                    INNER JOIN therapist_bill_payments tbp
                        ON tbp.id = tbpa.therapist_bill_payment_id
                        AND tbp.deleted_at IS NULL
                    GROUP BY tbpa.therapist_bill_id
                ) AS payments'),
                'therapist_bills.id',
                '=',
                'payments.therapist_bill_id'
            )
            ->where('therapist_bills.status', '!=', $paidStatus->value)
            ->first();

        return [
            'total_billed' => (float) ($outstandingPayables->total_billed ?? 0),
            'total_paid' => (float) ($outstandingPayables->total_paid ?? 0),
        ];
    }

    public function getOverdueBillsCount(TherapistBillStatus $paidStatus, Carbon $now): int
    {
        return TherapistBill::where('status', '!=', $paidStatus->value)
            ->where('due_date', '<', $now)
            ->count();
    }
}

