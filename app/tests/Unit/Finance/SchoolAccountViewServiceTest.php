<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\SchoolAccountViewService;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAccountViewServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolAccountViewService $service;

    private LedgerService $ledger;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SchoolAccountViewService::class);
        $this->ledger = app(LedgerService::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_includes_session_logs_for_paid_bills_only(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        $paidBill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::PAID->value,
            'paid_at' => now(),
        ]);

        $unpaidBill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT->value,
        ]);

        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $paidBill->id,
            'school_invoice_amount' => 150.00,
        ]);

        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $unpaidBill->id,
            'school_invoice_amount' => 200.00,
        ]);

        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => null,
            'school_invoice_amount' => 300.00,
        ]);

        $rows = $this->service->getTransactions($school);

        $charges = $rows->where('type', 'charge');
        $this->assertCount(1, $charges);
        $this->assertEqualsWithDelta(150.00, (float) $charges->first()['debit'], 0.001);
    }

    public function test_multi_school_bill_attributes_only_this_school_sessions(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::PAID->value,
        ]);

        SessionLog::factory()->create([
            'school_id' => $schoolA->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'school_invoice_amount' => 100.00,
        ]);
        SessionLog::factory()->create([
            'school_id' => $schoolB->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'school_invoice_amount' => 250.00,
        ]);

        $rowsA = $this->service->getTransactions($schoolA)->where('type', 'charge');
        $rowsB = $this->service->getTransactions($schoolB)->where('type', 'charge');

        $this->assertCount(1, $rowsA);
        $this->assertEqualsWithDelta(100.00, (float) $rowsA->first()['debit'], 0.001);
        $this->assertCount(1, $rowsB);
        $this->assertEqualsWithDelta(250.00, (float) $rowsB->first()['debit'], 0.001);
    }

    public function test_merges_payments_credit_notes_and_refunds_excluding_invoice_generated(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        $bill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::PAID->value,
        ]);
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'therapist_bill_id' => $bill->id,
            'school_invoice_amount' => 200.00,
        ]);

        // Existing canonical-ledger entries.
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 500.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 50.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: 'Cheque',
            recordedById: $this->admin->id,
        );
        $this->ledger->createCreditNoteForSchool($school->id, 30.00, null, $this->admin->id, now()->subDay());
        $this->ledger->createRefundForSchool($school->id, 10.00, null, $this->admin->id, now());

        $rows = $this->service->getTransactions($school);

        // Should not include INVOICE_GENERATED rows.
        $this->assertCount(0, $rows->where('type', TransactionType::INVOICE_GENERATED->value));

        $this->assertCount(1, $rows->where('type', 'charge'));
        $this->assertCount(1, $rows->where('type', TransactionType::PAYMENT_RECEIVED->value));
        $this->assertCount(1, $rows->where('type', TransactionType::CREDIT_NOTE->value));
        $this->assertCount(1, $rows->where('type', TransactionType::REFUND->value));

        // Running balance: charge +200, payment -50, credit_note -30, refund +10 = 130.
        $this->assertEqualsWithDelta(130.00, $this->service->getCurrentBalance($school), 0.001);
    }

    public function test_refund_is_a_debit_and_payment_is_a_credit(): void
    {
        $school = School::factory()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 75.00,
            recordedAt: now()->subDay(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createRefundForSchool($school->id, 25.00, null, $this->admin->id, now());

        $rows = $this->service->getTransactions($school);

        $payment = $rows->firstWhere('type', TransactionType::PAYMENT_RECEIVED->value);
        $refund = $rows->firstWhere('type', TransactionType::REFUND->value);

        $this->assertNotNull($payment);
        $this->assertNotNull($refund);
        $this->assertNull($payment['debit']);
        $this->assertEqualsWithDelta(75.00, (float) $payment['credit'], 0.001);
        $this->assertEqualsWithDelta(25.00, (float) $refund['debit'], 0.001);
        $this->assertNull($refund['credit']);
    }

    public function test_returns_empty_collection_for_school_without_activity(): void
    {
        $school = School::factory()->create();

        $rows = $this->service->getTransactions($school);

        $this->assertCount(0, $rows);
        $this->assertSame(0.0, $this->service->getCurrentBalance($school));
    }
}
