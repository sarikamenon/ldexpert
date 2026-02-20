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

    public function test_record_payment_creates_single_allocation_for_invoice(): void
    {
        $admin = User::factory()->create();
        $school = School::factory()->create();

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'invoice_date' => now()->subDays(5),
            'status' => InvoiceStatus::SENT,
            'total' => 100,
        ]);

        $service = app(InvoicePaymentService::class);

        $dto = new RecordInvoicePaymentDTO(
            invoiceId: $invoice->id,
            paidAt: now()->toDateString(),
            amount: 100.00,
            method: PaymentMethod::CHECK,
            reference: 'INV-001',
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
        ]);

        $this->assertEquals(1, InvoicePaymentAllocation::where('invoice_payment_id', $payment->id)->count());
        $this->assertTrue($invoice->fresh()->isFullyPaid());
    }

    public function test_record_payment_throws_without_invoice(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record payment without an invoice.');

        $service = app(InvoicePaymentService::class);
        $dto = new RecordInvoicePaymentDTO(
            invoiceId: 0,
            paidAt: now()->toDateString(),
            amount: 100.00,
            method: PaymentMethod::CHECK,
            reference: null,
            notes: null,
            recordedById: null,
        );
        $service->recordPayment($dto);
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

        $this->assertTrue($invoice->fresh()->isFullyPaid());
        $this->assertEquals(1, InvoicePaymentAllocation::where('invoice_payment_id', $payment->id)->count());

        $service->deletePayment($payment);

        $this->assertDatabaseMissing('invoice_payment_allocations', [
            'invoice_payment_id' => $payment->id,
        ]);

        $this->assertFalse($invoice->fresh()->isFullyPaid());
        $this->assertEquals(0.0, $invoice->fresh()->total_paid);
    }
}
