<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Domain\Finance\Services\TherapistBillPaymentService;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;
use App\Models\TherapistBillPaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistBillPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lump_sum_allocates_to_oldest_bills_and_updates_statuses(): void
    {
        $admin = User::factory()->create();
        $therapist = User::factory()->create();

        $bills = TherapistBill::factory()
            ->count(3)
            ->sequence(
                ['bill_date' => now()->subDays(3), 'status' => TherapistBillStatus::SENT, 'total_due' => 200],
                ['bill_date' => now()->subDays(2), 'status' => TherapistBillStatus::SENT, 'total_due' => 200],
                ['bill_date' => now()->subDay(), 'status' => TherapistBillStatus::SENT, 'total_due' => 200],
            )
            ->create([
                'therapist_id' => $therapist->id,
            ]);

        $service = app(TherapistBillPaymentService::class);

        $dto = new RecordTherapistBillPaymentDTO(
            therapistBillId: $bills[0]->id,
            paidAt: now()->toDateString(),
            amount: 250.00,
            method: PaymentMethod::DIRECT_DEPOSIT,
            reference: 'THER-LUMP-001',
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertDatabaseHas('therapist_bill_payments', [
            'id' => $payment->id,
            'amount' => 250.00,
        ]);

        $this->assertEquals(2, TherapistBillPaymentAllocation::count());

        $this->assertEquals(TherapistBillStatus::PAID, $bills[0]->fresh()->status);
        $this->assertTrue($bills[1]->fresh()->isPartiallyPaid());
        $this->assertTrue($bills[2]->fresh()->isSent());
    }

    public function test_delete_payment_removes_allocations_and_resets_statuses(): void
    {
        $admin = User::factory()->create();
        $therapist = User::factory()->create();

        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT,
            'total_due' => 300.00,
        ]);

        $service = app(TherapistBillPaymentService::class);

        $dto = new RecordTherapistBillPaymentDTO(
            therapistBillId: $bill->id,
            paidAt: now()->toDateString(),
            amount: 300.00,
            method: PaymentMethod::DIRECT_DEPOSIT,
            reference: null,
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertTrue($bill->fresh()->isPaid());
        $this->assertEquals(1, TherapistBillPaymentAllocation::where('therapist_bill_payment_id', $payment->id)->count());

        $service->deletePayment($payment);

        $this->assertDatabaseMissing('therapist_bill_payment_allocations', [
            'therapist_bill_payment_id' => $payment->id,
        ]);

        $this->assertFalse($bill->fresh()->isPaid());
        $this->assertEquals(0.0, $bill->fresh()->total_paid);
    }
}
