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
        $outstandingReceivables = DB::table('invoices')
            ->select(
                DB::raw('SUM(total) as total_invoiced'),
                DB::raw('COALESCE(SUM(payments.amount_paid), 0) as total_paid')
            )
            ->leftJoin(
                DB::raw('(SELECT invoice_id, SUM(amount) as amount_paid FROM invoice_payments GROUP BY invoice_id) as payments'),
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

        // Outstanding payables (unpaid bills)
        $outstandingPayables = DB::table('therapist_bills')
            ->select(
                DB::raw('SUM(total_due) as total_billed'),
                DB::raw('COALESCE(SUM(payments.amount_paid), 0) as total_paid')
            )
            ->leftJoin(
                DB::raw('(SELECT therapist_bill_id, SUM(amount) as amount_paid FROM therapist_bill_payments GROUP BY therapist_bill_id) as payments'),
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
