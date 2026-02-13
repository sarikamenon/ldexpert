<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $school = School::factory()->create();

        $this->invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'status' => InvoiceStatus::SENT,
            'total' => 1000.00,
        ]);
    }

    public function test_admin_can_record_payment_for_invoice(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.invoices.payments.store', $this->invoice),
            [
                'paid_at' => '2026-02-13',
                'amount' => 500.00,
                'method' => PaymentMethod::CHECK->value,
                'reference' => 'CHK-12345',
                'notes' => 'Partial payment',
            ]
        );

        $response->assertRedirect(route('admin.invoices.show', $this->invoice));
        $response->assertSessionHas('success');

        $payment = InvoicePayment::first();

        $this->assertNotNull($payment);

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->id,
            'amount' => 500.00,
            'method' => PaymentMethod::CHECK->value,
            'reference' => 'CHK-12345',
        ]);

        $this->assertDatabaseHas('invoice_payment_allocations', [
            'invoice_id' => $this->invoice->id,
            'invoice_payment_id' => $payment->id,
            'allocated_amount' => 500.00,
        ]);
    }

    public function test_invoice_status_updates_to_paid_when_fully_paid(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.invoices.payments.store', $this->invoice),
            [
                'paid_at' => '2026-02-13',
                'amount' => 1000.00,
                'method' => PaymentMethod::BANK_TRANSFER->value,
            ]
        );

        $this->invoice->refresh();

        $this->assertTrue($this->invoice->isPaid());
        $this->assertEquals(InvoiceStatus::PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);
    }

    public function test_ledger_entry_created_on_payment(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.invoices.payments.store', $this->invoice),
            [
                'paid_at' => '2026-02-13',
                'amount' => 500.00,
                'method' => PaymentMethod::CHECK->value,
            ]
        );

        $this->assertDatabaseHas('ledger_entries', [
            'ledgerable_type' => School::class,
            'ledgerable_id' => $this->invoice->school_id,
            'transaction_type' => 'payment_received',
            'amount' => 500.00,
        ]);
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.invoices.payments.store', $this->invoice),
            [
                'paid_at' => '2026-02-13',
                'amount' => 0,
                'method' => PaymentMethod::CHECK->value,
            ]
        );

        $response->assertSessionHasErrors('amount');
    }

    public function test_payment_date_cannot_be_in_future(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.invoices.payments.store', $this->invoice),
            [
                'paid_at' => now()->addDays(1)->format('Y-m-d'),
                'amount' => 500.00,
                'method' => PaymentMethod::CHECK->value,
            ]
        );

        $response->assertSessionHasErrors('paid_at');
    }

    public function test_admin_can_delete_payment(): void
    {
        $payment = InvoicePayment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'amount' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.invoices.payments.destroy', [$this->invoice, $payment])
        );

        $response->assertRedirect(route('admin.invoices.show', $this->invoice));
        $this->assertSoftDeleted('invoice_payments', ['id' => $payment->id]);
    }

    public function test_balance_calculations_are_correct(): void
    {
        $payment1 = InvoicePayment::factory()->create(['amount' => 300.00]);
        $payment2 = InvoicePayment::factory()->create(['amount' => 200.00]);

        \App\Models\InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_payment_id' => $payment1->id,
            'allocated_amount' => 300.00,
        ]);

        \App\Models\InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_payment_id' => $payment2->id,
            'allocated_amount' => 200.00,
        ]);

        $this->invoice->refresh();

        $this->assertEquals(500.00, $this->invoice->total_paid);
        $this->assertEquals(500.00, $this->invoice->balance_remaining);
        $this->assertTrue($this->invoice->isPartiallyPaid());
        $this->assertFalse($this->invoice->isFullyPaid());
    }
}
