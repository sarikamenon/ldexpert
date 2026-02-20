<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Enums\InvoiceStatus;
use App\Enums\TherapistBillStatus;
use Illuminate\Support\Carbon;

interface FinanceSummaryRepositoryInterface
{
    public function getRevenueInvoiced(Carbon $start, Carbon $end): float;

    public function getRevenueCollected(Carbon $start, Carbon $end): float;

    public function getTherapistCosts(Carbon $start, Carbon $end): float;

    public function getTherapistPayments(Carbon $start, Carbon $end): float;

    public function getOtherExpenses(Carbon $start, Carbon $end): float;

    /**
     * @return array{total_invoiced: float, total_paid: float}
     */
    public function getOutstandingReceivablesTotals(InvoiceStatus $paidStatus): array;

    public function getOverdueInvoicesCount(InvoiceStatus $paidStatus, Carbon $now): int;

    /**
     * @return array{total_billed: float, total_paid: float}
     */
    public function getOutstandingPayablesTotals(TherapistBillStatus $paidStatus): array;

    public function getOverdueBillsCount(TherapistBillStatus $paidStatus, Carbon $now): int;
}
