<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\FinanceSummaryRepositoryInterface;
use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\TherapistBillPayment;

final class FinanceDashboardService
{
    public function __construct(
        private readonly FinanceSummaryRepositoryInterface $summaryRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $revenueInvoiced = $this->summaryRepository->getRevenueInvoiced($startOfMonth, $endOfMonth);
        $revenueCollected = $this->summaryRepository->getRevenueCollected($startOfMonth, $endOfMonth);
        $therapistCosts = $this->summaryRepository->getTherapistCosts($startOfMonth, $endOfMonth);
        $therapistPayments = $this->summaryRepository->getTherapistPayments($startOfMonth, $endOfMonth);
        $otherExpenses = $this->summaryRepository->getOtherExpenses($startOfMonth, $endOfMonth);

        // For the dashboard we treat cash paid to therapists plus other operating
        // expenses as the total expense burden when comparing to cash collected.
        $totalExpenses = $therapistPayments + $otherExpenses;
        $netIncome = $revenueCollected - $therapistPayments - $otherExpenses;

        $receivablesTotals = $this->summaryRepository->getOutstandingReceivablesTotals(InvoiceStatus::PAID);
        $arTotal = $receivablesTotals['total_invoiced'] - $receivablesTotals['total_paid'];

        $overdueInvoicesCount = $this->summaryRepository->getOverdueInvoicesCount(InvoiceStatus::PAID, now());

        $payablesTotals = $this->summaryRepository->getOutstandingPayablesTotals(TherapistBillStatus::PAID);
        $apTotal = $payablesTotals['total_billed'] - $payablesTotals['total_paid'];

        $overdueBillsCount = $this->summaryRepository->getOverdueBillsCount(TherapistBillStatus::PAID, now());

        $recentPaymentsReceived = InvoicePayment::with(['school', 'recordedBy'])
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $recentPaymentsMade = TherapistBillPayment::with(['therapist', 'recordedBy'])
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $recentExpenses = Expense::with(['category', 'createdBy'])
            ->orderBy('expense_date', 'desc')
            ->limit(5)
            ->get();

        return [
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
        ];
    }
}
