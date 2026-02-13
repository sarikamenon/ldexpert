<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Domain\Finance\Services\InvoicePaymentService;
use App\DTOs\RecordInvoicePaymentDTO;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lump_sum_allocates_to_oldest_invoices_and_updates_statuses(): void
    {
        $admin = User::factory()->create();
        $school = School::factory()->create();

        $invoices = Invoice::factory()
            ->count(5)
            ->sequence(
                ['invoice_date' => now()->subDays(5), 'status' => InvoiceStatus::SENT, 'total' => 100],
                ['invoice_date' => now()->subDays(4), 'status' => InvoiceStatus::SENT, 'total' => 100],
                ['invoice_date' => now()->subDays(3), 'status' => InvoiceStatus::SENT, 'total' => 100],
                ['invoice_date' => now()->subDays(2), 'status' => InvoiceStatus::SENT, 'total' => 100],
                ['invoice_date' => now()->subDay(), 'status' => InvoiceStatus::SENT, 'total' => 100],
            )
            ->create([
                'school_id' => $school->id,
            ]);

        $service = app(InvoicePaymentService::class);

        $dto = new RecordInvoicePaymentDTO(
            invoiceId: $invoices[0]->id,
            paidAt: now()->toDateString(),
            amount: 350.00,
            method: PaymentMethod::CHECK,
            reference: 'LUMP-001',
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->id,
            'amount' => 350.00,
        ]);

        $this->assertEquals(4, InvoicePaymentAllocation::count());

        $this->assertEquals(InvoiceStatus::PAID, $invoices[0]->fresh()->status);
        $this->assertEquals(InvoiceStatus::PAID, $invoices[1]->fresh()->status);
        $this->assertEquals(InvoiceStatus::PAID, $invoices[2]->fresh()->status);
        $this->assertTrue($invoices[3]->fresh()->isPartiallyPaid());
        $this->assertTrue($invoices[4]->fresh()->isSent());
    }

    public function test_delete_payment_removes_allocations_and_resets_statuses(): void
    {
        $admin = User::factory()->create();
        $school = School::factory()->create();

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'total' => 200.00,
        ]);

        $service = app(InvoicePaymentService::class);

        $dto = new RecordInvoicePaymentDTO(
            invoiceId: $invoice->id,
            paidAt: now()->toDateString(),
            amount: 200.00,
            method: PaymentMethod::BANK_TRANSFER,
            reference: null,
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertTrue($invoice->fresh()->isPaid());
        $this->assertEquals(1, InvoicePaymentAllocation::where('invoice_payment_id', $payment->id)->count());

        $service->deletePayment($payment);

        $this->assertDatabaseMissing('invoice_payment_allocations', [
            'invoice_payment_id' => $payment->id,
        ]);

        $this->assertFalse($invoice->fresh()->isPaid());
        $this->assertEquals(0.0, $invoice->fresh()->total_paid);
    }
}
