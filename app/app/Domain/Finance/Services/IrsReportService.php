<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\IrsReportFilterDTO;
use App\Enums\PaymentMethod;
use App\Models\TherapistBillPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class IrsReportService
{
    /**
     * Get IRS report rows (one per payment) with recipient, payment date, hourly rate, payroll period, amounts, YTD.
     *
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function getReportData(IrsReportFilterDTO $filters): array
    {
        $query = TherapistBillPayment::query()
            ->with(['therapist.therapistProfile', 'therapistBill'])
            ->orderBy('paid_at')
            ->orderBy('therapist_id');

        if ($filters->dateFrom === null && $filters->dateTo === null) {
            return [
                'rows' => [],
                'summary' => [
                    'total_gross' => 0.0,
                    'total_net' => 0.0,
                    'row_count' => 0,
                ],
            ];
        }
        if ($filters->dateFrom !== null) {
            $query->whereDate('paid_at', '>=', $filters->dateFrom);
        }
        if ($filters->dateTo !== null) {
            $query->whereDate('paid_at', '<=', $filters->dateTo);
        }
        if ($filters->therapistIds !== null && $filters->therapistIds !== []) {
            $query->whereIn('therapist_id', $filters->therapistIds);
        }

        $payments = $query->get();

        if ($payments->isEmpty()) {
            return [
                'rows' => [],
                'summary' => [
                    'total_gross' => 0.0,
                    'total_net' => 0.0,
                    'row_count' => 0,
                ],
            ];
        }

        $ytdByPaymentId = $this->computeYtdLookup($payments);

        $rows = [];
        $totalGross = 0.0;
        $totalNet = 0.0;

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;
            $therapist = $payment->therapist;
            $profile = $therapist?->therapistProfile;
            $bill = $payment->therapistBill;

            $recipient = $bill?->therapist_name ?? $therapist?->name ?? '-';
            $hourlyRate = $profile ? (float) $profile->hourly_rate : 0.0;

            $method = $payment->method;
            if ($method instanceof PaymentMethod) {
                $paymentMethod = $method->label();
            } else {
                $paymentMethod = $method !== null ? (string) $method : '';
            }

            $billingStart = $bill?->billing_period_start;
            $billingEnd = $bill?->billing_period_end;
            if ($billingStart instanceof CarbonInterface && $billingEnd instanceof CarbonInterface) {
                $payrollPeriod = $billingStart->format('F j').' to '.$billingEnd->format('F j');
            } else {
                $payrollPeriod = '-';
            }

            $ytd = $ytdByPaymentId[$payment->id] ?? 0.0;

            $additionalPay = 0.0;
            $otherPay1 = 0.0;
            $otherPay2 = 0.0;
            $federalTax = 0.0;
            $federalMedTax = 0.0;
            $otherDeduction1 = 0.0;
            $otherDeduction2 = 0.0;
            $totalDeductions = $federalTax + $federalMedTax + $otherDeduction1 + $otherDeduction2;
            $netPay = $amount - $totalDeductions;
            $rowTotalGross = $amount + $additionalPay + $otherPay1 + $otherPay2;

            $rows[] = [
                'company_name' => config('finance.company_name', 'The LD Expert, LLC'),
                'recipient' => $recipient,
                'payment_date' => $payment->paid_at instanceof CarbonInterface ? $payment->paid_at->format('Y-m-d') : (string) $payment->paid_at,
                'payment_date_display' => $payment->paid_at instanceof CarbonInterface ? $payment->paid_at->format('m/d/y') : (string) $payment->paid_at,
                'payment_date_csv' => $payment->paid_at instanceof CarbonInterface ? $payment->paid_at->format('d/m/y') : (string) $payment->paid_at,
                'payment_method' => $paymentMethod,
                'hourly_rate' => $hourlyRate,
                'tax_status' => config('finance.irs_tax_status', '1099-NEC'),
                'payroll_period' => $payrollPeriod,
                'additional_percentage' => 0.0,
                'additional_amount' => 0.0,
                'regular_pay' => $amount,
                'additional_pay' => $additionalPay,
                'other_pay_1' => $otherPay1,
                'other_pay_2' => $otherPay2,
                'federal_tax' => $federalTax,
                'federal_med_tax' => $federalMedTax,
                'other_deduction_1' => $otherDeduction1,
                'other_deduction_2' => $otherDeduction2,
                'ytd_regular_pay' => $ytd,
                'ytd_additional_pay' => 0.0,
                'ytd_federal_tax' => 0.0,
                'ytd_federal_med_tax' => 0.0,
                'total_gross' => $rowTotalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $netPay,
            ];

            $totalGross += $rowTotalGross;
            $totalNet += $netPay;
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_gross' => round($totalGross, 2),
                'total_net' => round($totalNet, 2),
                'row_count' => count($rows),
            ],
        ];
    }

    /**
     * Get report rows for DataTables: same filters as getReportData, then search/sort/slice.
     *
     * @return array{recordsTotal: int, recordsFiltered: int, rows: array<int, array<string, mixed>>}
     */
    public function getReportDataForDataTables(IrsReportFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $data = $this->getReportData($filters);
        $rows = $data['rows'];
        $recordsTotal = count($rows);

        if ($params->searchValue !== null && $params->searchValue !== '') {
            $sv = mb_strtolower($params->searchValue);
            $rows = array_values(array_filter($rows, function (array $row) use ($sv): bool {
                foreach (['recipient', 'payment_date_display', 'payment_method', 'tax_status', 'payroll_period'] as $key) {
                    if (isset($row[$key]) && str_contains(mb_strtolower((string) $row[$key]), $sv)) {
                        return true;
                    }
                }

                return false;
            }));
        }
        $recordsFiltered = count($rows);

        $orderKey = $params->orderColumn ?? 'payment_date';
        $dir = $params->orderDir === 'desc' ? -1 : 1;
        usort($rows, function (array $a, array $b) use ($orderKey, $dir): int {
            $va = $a[$orderKey] ?? '';
            $vb = $b[$orderKey] ?? '';
            if (is_numeric($va) && is_numeric($vb)) {
                return (int) (($va <=> $vb) * $dir);
            }

            return strcmp((string) $va, (string) $vb) * $dir;
        });

        $rows = array_slice($rows, $params->start, $params->length);

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * YTD (year-to-date) for each payment: sum of that therapist's payments in that calendar year up to and including paid_at.
     *
     * @param  Collection<int, TherapistBillPayment>  $payments
     * @return array<int, float> payment_id => ytd amount
     */
    private function computeYtdLookup(Collection $payments): array
    {
        $therapistIds = $payments->pluck('therapist_id')->unique()->values()->all();
        $years = $payments->pluck('paid_at')->map(fn ($d) => $d->year)->unique()->values()->all();

        if ($therapistIds === [] || $years === []) {
            return array_fill_keys($payments->pluck('id')->all(), 0.0);
        }

        $allPayments = TherapistBillPayment::query()
            ->whereIn('therapist_id', $therapistIds)
            ->whereRaw('YEAR(paid_at) IN ('.implode(',', array_map('intval', $years)).')')
            ->orderBy('paid_at')
            ->get(['id', 'therapist_id', 'paid_at', 'amount']);

        $byPaymentId = [];
        foreach ($payments as $payment) {
            $byPaymentId[$payment->id] = (float) $allPayments
                ->where('therapist_id', $payment->therapist_id)
                ->filter(fn ($p) => $p->paid_at->year === $payment->paid_at->year && $p->paid_at->lte($payment->paid_at))
                ->sum('amount');
        }

        return $byPaymentId;
    }
}
