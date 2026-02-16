<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    /**
     * Display the finance dashboard.
     */
    public function index(): View
    {
        // Get current month date range
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Revenue (invoices sent this month)
        $revenueInvoiced = Invoice::where('status', '!=', InvoiceStatus::DRAFT)
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->sum('total');

        // Revenue collected (payments received this month)
        $revenueCollected = InvoicePayment::whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Therapist costs (bills sent this month)
        $therapistCosts = TherapistBill::where('status', '!=', TherapistBillStatus::DRAFT)
            ->whereBetween('bill_date', [$startOfMonth, $endOfMonth])
            ->sum('total_due');

        // Therapist payments (payments made this month)
        $therapistPayments = TherapistBillPayment::whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Other expenses (expenses this month)
        $otherExpenses = Expense::whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Total expenses
        $totalExpenses = (float) $therapistPayments + (float) $otherExpenses;

        // Net income (revenue collected - expenses)
        $netIncome = (float) $revenueCollected - $totalExpenses;

        // Outstanding receivables (unpaid invoices)
        // NOTE: Payments are linked to invoices via the invoice_payment_allocations table,
        // not a direct invoice_id column on invoice_payments. We therefore aggregate the
        // allocated amounts per invoice using that pivot table and ignore soft-deleted
        // payments.
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
            ->where('invoices.status', '!=', InvoiceStatus::PAID)
            ->first();

        $arTotal = ((float) ($outstandingReceivables->total_invoiced ?? 0)) - ((float) ($outstandingReceivables->total_paid ?? 0));

        // Count of overdue invoices
        $overdueInvoicesCount = Invoice::where('status', '!=', InvoiceStatus::PAID)
            ->where('due_date', '<', now())
            ->count();

        // Outstanding payables (unpaid therapist bills)
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
            ->where('therapist_bills.status', '!=', TherapistBillStatus::PAID)
            ->first();

        $apTotal = ((float) ($outstandingPayables->total_billed ?? 0)) - ((float) ($outstandingPayables->total_paid ?? 0));

        // Count of overdue bills
        $overdueBillsCount = TherapistBill::where('status', '!=', TherapistBillStatus::PAID)
            ->where('due_date', '<', now())
            ->count();

        // Recent transactions (last 10)
        $recentPaymentsReceived = InvoicePayment::with(['invoice.school', 'recordedBy'])
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $recentPaymentsMade = TherapistBillPayment::with(['therapistBill.therapist', 'recordedBy'])
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $recentExpenses = Expense::with(['category', 'createdBy'])
            ->orderBy('expense_date', 'desc')
            ->limit(5)
            ->get();

        return view('admin.finance.dashboard', [
            'revenueInvoiced' => $revenueInvoiced,
            'revenueCollected' => $revenueCollected,
            'therapistCosts' => $therapistCosts,
            'therapistPayments' => $therapistPayments,
            'otherExpenses' => $otherExpenses,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'arTotal' => $arTotal,
            'overdueInvoicesCount' => $overdueInvoicesCount,
            'apTotal' => $apTotal,
            'overdueBillsCount' => $overdueBillsCount,
            'recentPaymentsReceived' => $recentPaymentsReceived,
            'recentPaymentsMade' => $recentPaymentsMade,
            'recentExpenses' => $recentExpenses,
            'currentMonth' => now()->format('F Y'),
        ]);
    }
}
