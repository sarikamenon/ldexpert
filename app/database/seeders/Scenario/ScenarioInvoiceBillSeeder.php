<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Domain\Billing\Services\TherapistBillService;
use App\Domain\Finance\Services\InvoicePaymentService;
use App\Domain\Finance\Services\TherapistBillPaymentService;
use App\Domain\Invoice\Services\InvoiceService;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\CreateTherapistBillDTO;
use App\DTOs\RecordInvoicePaymentDTO;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SessionLogStatus;
use App\Enums\TherapistBillStatus;
use App\Models\Invoice;
use App\Models\SessionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class ScenarioInvoiceBillSeeder extends Seeder
{
    /**
     * Create biweekly school invoices and half-month therapist bills from approved session logs (2025),
     * then record payments (invoice: 2 weeks after period end; bill: 1 month after period end).
     */
    public function run(): void
    {
        $admin = User::query()->where('role', Role::ADMIN->value)->first();
        if (! $admin) {
            $this->command?->warn('ScenarioInvoiceBillSeeder: no admin user found.');

            return;
        }

        $invoiceService = app(InvoiceService::class);
        $billService = app(TherapistBillService::class);
        $invoicePaymentService = app(InvoicePaymentService::class);
        $billPaymentService = app(TherapistBillPaymentService::class);

        $logs = SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->whereYear('session_date', 2025)
            ->whereNull('invoice_id')
            ->whereNull('therapist_bill_id')
            ->get();

        $bySchoolPeriod = $this->groupBySchoolBiweekly($logs);
        $invSeq = 1;
        foreach ($bySchoolPeriod as $key => $logIds) {
            [$schoolId, $start, $end] = explode('|', $key);
            $startDate = $start;
            $endDate = $end;
            $dueDate = Carbon::parse($endDate)->addDays(14)->format('Y-m-d');
            $paidAt = Carbon::parse($endDate)->addDays(14)->format('Y-m-d');

            try {
                $dto = new CreateInvoiceDTO(
                    schoolId: (int) $schoolId,
                    invoiceDate: $endDate,
                    invoiceNumber: null,
                    billingPeriodStart: $startDate,
                    billingPeriodEnd: $endDate,
                    sessionLogIds: $logIds,
                    notes: 'Scenario 2025 biweekly invoice'
                );
                $invoice = $invoiceService->generateInvoice($admin, $dto);
                $invoice->update(['due_date' => $dueDate]);

                $paymentDto = RecordInvoicePaymentDTO::fromArray([
                    'invoice_id' => $invoice->id,
                    'paid_at' => $paidAt,
                    'amount' => (float) $invoice->total,
                    'method' => PaymentMethod::CHECK->value,
                    'reference' => 'SCEN-INV-'.$invSeq,
                    'notes' => 'Scenario 2025 payment',
                    'recorded_by_id' => $admin->id,
                    'school_id' => $invoice->school_id,
                ]);
                $invoicePaymentService->recordPayment($paymentDto);
                $invoice->update([
                    'status' => InvoiceStatus::PAID->value,
                    'paid_at' => $paidAt,
                ]);
                $invSeq++;
            } catch (\Throwable $e) {
                $this->command?->warn("Invoice skip: {$e->getMessage()}");
            }
        }

        $byTherapistPeriod = $this->groupByTherapistHalfMonth($logs);
        $billSeq = 1;
        foreach ($byTherapistPeriod as $key => $logIds) {
            [$therapistId, $start, $end] = explode('|', $key);
            $dueDate = $this->billDueDate($end);
            $paidAt = $this->billPaymentDate($end);

            try {
                $dto = new CreateTherapistBillDTO(
                    therapistId: (int) $therapistId,
                    billDate: $end,
                    billNumber: null,
                    billingPeriodStart: $start,
                    billingPeriodEnd: $end,
                    sessionLogIds: $logIds,
                    dueDate: $dueDate,
                    notes: 'Scenario 2025 half-month bill'
                );
                $bill = $billService->generateBill($admin, $dto);

                $paymentDto = RecordTherapistBillPaymentDTO::fromArray([
                    'therapist_bill_id' => $bill->id,
                    'paid_at' => $paidAt,
                    'amount' => (float) $bill->total_due,
                    'method' => PaymentMethod::DIRECT_DEPOSIT->value,
                    'reference' => 'SCEN-BILL-'.$billSeq,
                    'notes' => 'Scenario 2025 payment',
                    'recorded_by_id' => $admin->id,
                    'therapist_id' => $bill->therapist_id,
                ]);
                $billPaymentService->recordPayment($paymentDto);
                $bill->update([
                    'status' => TherapistBillStatus::PAID->value,
                    'paid_at' => $paidAt,
                ]);
                $billSeq++;
            } catch (\Throwable $e) {
                $this->command?->warn("Bill skip: {$e->getMessage()}");
            }
        }
    }

    /**
     * @return array<string, array<int>>
     */
    private function groupBySchoolBiweekly(Collection $logs): array
    {
        $periods = $this->biweeklyPeriods2025();
        $grouped = [];
        foreach ($logs as $log) {
            if (! $log->school_id || ! $log->is_billable_school) {
                continue;
            }
            $d = $log->session_date->format('Y-m-d');
            foreach ($periods as [$start, $end]) {
                if ($d >= $start && $d <= $end) {
                    $key = "{$log->school_id}|{$start}|{$end}";
                    if (! isset($grouped[$key])) {
                        $grouped[$key] = [];
                    }
                    $grouped[$key][] = $log->id;
                    break;
                }
            }
        }

        return $grouped;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function biweeklyPeriods2025(): array
    {
        $periods = [];
        $start = Carbon::create(2025, 8, 1);
        $end = Carbon::create(2025, 12, 31);
        $current = $start->copy();
        while ($current->lte($end)) {
            $periodEnd = $current->copy()->addDays(13);
            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }
            $periods[] = [$current->format('Y-m-d'), $periodEnd->format('Y-m-d')];
            $current->addDays(14);
        }

        return $periods;
    }

    /**
     * @return array<string, array<int>>
     */
    private function groupByTherapistHalfMonth(Collection $logs): array
    {
        $grouped = [];
        foreach ($logs as $log) {
            if (! $log->is_billable_therapist) {
                continue;
            }
            $d = Carbon::parse($log->session_date);
            $start = $d->day <= 15
                ? $d->copy()->startOfMonth()->format('Y-m-d')
                : $d->copy()->startOfMonth()->addDays(15)->format('Y-m-d');
            $end = $d->day <= 15
                ? $d->copy()->startOfMonth()->addDays(14)->format('Y-m-d')
                : $d->copy()->endOfMonth()->format('Y-m-d');
            $key = "{$log->therapist_id}|{$start}|{$end}";
            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $log->id;
        }

        return $grouped;
    }

    private function billDueDate(string $periodEnd): string
    {
        $d = Carbon::parse($periodEnd);

        return $d->addMonth()->format('Y-m-d');
    }

    private function billPaymentDate(string $periodEnd): string
    {
        return Carbon::parse($periodEnd)->addMonth()->format('Y-m-d');
    }
}
