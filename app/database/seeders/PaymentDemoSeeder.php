<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\Services\InvoicePaymentService;
use App\Domain\Finance\Services\TherapistBillPaymentService;
use App\DTOs\RecordInvoicePaymentDTO;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentDemoSeeder extends Seeder
{
    /**
     * Seed demo payments for invoices and therapist bills using domain services.
     */
    public function run(): void
    {
        // Run demo payments in all non-production environments.
        if (app()->environment('production')) {
            return;
        }

        // Avoid reseeding demo payments.
        if (
            InvoicePayment::where('notes', 'like', '%[demo]%')->exists()
            || TherapistBillPayment::where('notes', 'like', '%[demo]%')->exists()
        ) {
            return;
        }

        /** @var User|null $admin */
        $admin = User::query()->orderBy('id')->first();

        /** @var InvoicePaymentService $invoicePaymentService */
        $invoicePaymentService = app(InvoicePaymentService::class);

        /** @var TherapistBillPaymentService $therapistBillPaymentService */
        $therapistBillPaymentService = app(TherapistBillPaymentService::class);

        $this->seedInvoicePayments($invoicePaymentService, $admin);
        $this->seedTherapistBillPayments($therapistBillPaymentService, $admin);
    }

    private function seedInvoicePayments(InvoicePaymentService $service, ?User $admin): void
    {
        $invoices = Invoice::query()
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->limit(30)
            ->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $methods = PaymentMethod::cases();

        foreach ($invoices as $index => $invoice) {
            $baseAmount = (float) $invoice->total;

            if ($baseAmount <= 0) {
                continue;
            }

            // Create a mix of fully paid and partially paid scenarios.
            $factor = match ($index % 3) {
                0 => 1.0,   // fully paid
                1 => 0.5,   // half paid
                default => 0.25, // partially paid
            };

            $amount = round($baseAmount * $factor, 2);

            if ($amount <= 0) {
                continue;
            }

            $data = [
                'invoice_id' => $invoice->id,
                'paid_at' => now()->subDays(random_int(1, 30))->format('Y-m-d'),
                'amount' => $amount,
                'method' => $methods[array_rand($methods)]->value,
                'reference' => sprintf('PAY-INV-%06d', $invoice->id),
                'notes' => 'Finance module demo payment [demo]',
                'recorded_by_id' => $admin?->id,
                'school_id' => $invoice->school_id,
            ];

            $dto = RecordInvoicePaymentDTO::fromArray($data);
            $service->recordPayment($dto);
        }
    }

    private function seedTherapistBillPayments(
        TherapistBillPaymentService $service,
        ?User $admin,
    ): void {
        $bills = TherapistBill::query()
            ->orderBy('bill_date')
            ->orderBy('id')
            ->limit(30)
            ->get();

        if ($bills->isEmpty()) {
            return;
        }

        $methods = PaymentMethod::cases();

        foreach ($bills as $index => $bill) {
            $baseAmount = (float) $bill->total_due;

            if ($baseAmount <= 0) {
                continue;
            }

            $factor = match ($index % 3) {
                0 => 1.0,
                1 => 0.5,
                default => 0.25,
            };

            $amount = round($baseAmount * $factor, 2);

            if ($amount <= 0) {
                continue;
            }

            $data = [
                'therapist_bill_id' => $bill->id,
                'paid_at' => now()->subDays(random_int(1, 30))->format('Y-m-d'),
                'amount' => $amount,
                'method' => $methods[array_rand($methods)]->value,
                'reference' => sprintf('PAY-BILL-%06d', $bill->id),
                'notes' => 'Finance module demo payment [demo]',
                'recorded_by_id' => $admin?->id,
                'therapist_id' => $bill->therapist_id,
            ];

            $dto = RecordTherapistBillPaymentDTO::fromArray($data);
            $service->recordPayment($dto);
        }
    }
}

