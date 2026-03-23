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

    public function test_record_payment_creates_single_allocation_for_bill(): void
    {
        $admin = User::factory()->create();
        $therapist = User::factory()->create();

        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'bill_date' => now()->subDays(3),
            'status' => TherapistBillStatus::SENT,
            'total_due' => 200,
        ]);

        $service = app(TherapistBillPaymentService::class);

        $dto = new RecordTherapistBillPaymentDTO(
            therapistBillId: $bill->id,
            paidAt: now()->toDateString(),
            amount: 200.00,
            method: PaymentMethod::DIRECT_DEPOSIT,
            reference: 'BILL-001',
            notes: null,
            recordedById: $admin->id,
        );

        $payment = $service->recordPayment($dto);

        $this->assertDatabaseHas('therapist_bill_payments', [
            'id' => $payment->id,
            'therapist_bill_id' => $bill->id,
            'amount' => 200.00,
        ]);

        $this->assertEquals(1, TherapistBillPaymentAllocation::where('therapist_bill_payment_id', $payment->id)->count());
        $this->assertTrue($bill->fresh()->isFullyPaid());
    }

    public function test_record_payment_throws_without_bill(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot record payment without a therapist bill.');

        $service = app(TherapistBillPaymentService::class);
        $dto = new RecordTherapistBillPaymentDTO(
            therapistBillId: 0,
            paidAt: now()->toDateString(),
            amount: 100.00,
            method: PaymentMethod::DIRECT_DEPOSIT,
            reference: null,
            notes: null,
            recordedById: null,
        );
        $service->recordPayment($dto);
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

        $this->assertTrue($bill->fresh()->isFullyPaid());
        $this->assertEquals(1, TherapistBillPaymentAllocation::where('therapist_bill_payment_id', $payment->id)->count());

        $service->deletePayment($payment);

        $this->assertDatabaseMissing('therapist_bill_payment_allocations', [
            'therapist_bill_payment_id' => $payment->id,
        ]);

        $this->assertFalse($bill->fresh()->isFullyPaid());
        $this->assertEquals(0.0, $bill->fresh()->total_paid);
    }
}
