<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TherapistBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::ADMIN]);
        $therapist = User::factory()->create(['role' => Role::THERAPIST]);

        $this->bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT,
            'total_due' => 800.00,
        ]);
    }

    public function test_admin_can_record_payment_for_therapist_bill(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.billing.therapist-bills.payments.store', $this->bill),
            [
                'paid_at' => '2026-02-13',
                'amount' => 400.00,
                'method' => PaymentMethod::DIRECT_DEPOSIT->value,
                'reference' => 'DD-67890',
                'notes' => 'Partial payment',
            ]
        );

        $response->assertRedirect(route('admin.billing.therapist-bills.show', $this->bill));
        $response->assertSessionHas('success');

        $payment = TherapistBillPayment::first();

        $this->assertNotNull($payment);

        $this->assertDatabaseHas('therapist_bill_payments', [
            'id' => $payment->id,
            'amount' => 400.00,
            'method' => PaymentMethod::DIRECT_DEPOSIT->value,
            'reference' => 'DD-67890',
        ]);

        $this->assertDatabaseHas('therapist_bill_payment_allocations', [
            'therapist_bill_id' => $this->bill->id,
            'therapist_bill_payment_id' => $payment->id,
            'allocated_amount' => 400.00,
        ]);
    }

    public function test_bill_status_updates_to_paid_when_fully_paid(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.billing.therapist-bills.payments.store', $this->bill),
            [
                'paid_at' => '2026-02-13',
                'amount' => 800.00,
                'method' => PaymentMethod::DIRECT_DEPOSIT->value,
            ]
        );

        $this->bill->refresh();

        $this->assertTrue($this->bill->isPaid());
        $this->assertEquals(TherapistBillStatus::PAID, $this->bill->status);
        $this->assertNotNull($this->bill->paid_at);
    }

    public function test_ledger_entry_created_on_payment(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.billing.therapist-bills.payments.store', $this->bill),
            [
                'paid_at' => '2026-02-13',
                'amount' => 400.00,
                'method' => PaymentMethod::DIRECT_DEPOSIT->value,
            ]
        );

        $this->assertDatabaseHas('ledger_entries', [
            'ledgerable_type' => User::class,
            'ledgerable_id' => $this->bill->therapist_id,
            'transaction_type' => 'payment_made',
            'amount' => 400.00,
        ]);
    }

    public function test_balance_calculations_are_correct(): void
    {
        $payment1 = TherapistBillPayment::factory()->create(['amount' => 300.00]);
        $payment2 = TherapistBillPayment::factory()->create(['amount' => 200.00]);

        \App\Models\TherapistBillPaymentAllocation::factory()->create([
            'therapist_bill_id' => $this->bill->id,
            'therapist_bill_payment_id' => $payment1->id,
            'allocated_amount' => 300.00,
        ]);

        \App\Models\TherapistBillPaymentAllocation::factory()->create([
            'therapist_bill_id' => $this->bill->id,
            'therapist_bill_payment_id' => $payment2->id,
            'allocated_amount' => 200.00,
        ]);

        $this->bill->refresh();

        $this->assertEquals(500.00, $this->bill->total_paid);
        $this->assertEquals(300.00, $this->bill->balance_remaining);
        $this->assertTrue($this->bill->isPartiallyPaid());
        $this->assertFalse($this->bill->isFullyPaid());
    }
}
