<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\Domain\Finance\Services\LedgerService;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerAccountStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LedgerService $ledger;

    private LedgerEntryRepositoryInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->ledger = app(LedgerService::class);
        $this->repo = app(LedgerEntryRepositoryInterface::class);
    }

    public function test_school_stats_returns_all_six_totals(): void
    {
        $school = School::factory()->create();

        // Two SENT invoices on the source-of-truth table; total_invoiced reads from invoices.
        Invoice::factory()->sent($this->admin)->create([
            'school_id' => $school->id,
            'subtotal' => 200.00,
            'tax_total' => 0,
            'total' => 200.00,
        ]);
        Invoice::factory()->sent($this->admin)->create([
            'school_id' => $school->id,
            'subtotal' => 100.00,
            'tax_total' => 0,
            'total' => 100.00,
        ]);

        // Mirror them on the ledger so the chain is realistic.
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 200.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 100.00,
            recordedAt: now()->subDays(4),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // Two payments totalling 120.
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 50.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 70.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // One credit note (40), one refund (15).
        $this->ledger->createCreditNoteForSchool($school->id, 40.00, null, $this->admin->id, now()->subDay());
        $this->ledger->createRefundForSchool($school->id, 15.00, null, $this->admin->id, now());

        $stats = $this->repo->getSchoolStats($school->id);

        // Source-of-truth counts/sums.
        $this->assertEqualsWithDelta(300.00, $stats['total_invoiced'], 0.001);
        $this->assertSame(2, $stats['invoice_count']);

        $this->assertEqualsWithDelta(120.00, $stats['total_paid'], 0.001);
        $this->assertSame(2, $stats['payment_count']);

        $this->assertEqualsWithDelta(40.00, $stats['total_credit_notes'], 0.001);
        $this->assertSame(1, $stats['credit_note_count']);

        $this->assertEqualsWithDelta(15.00, $stats['total_refunds'], 0.001);
        $this->assertSame(1, $stats['refund_count']);

        // Outstanding = invoiced − paid − credit notes + refunds (matches the chain tail).
        $this->assertEqualsWithDelta(155.00, $stats['outstanding'], 0.001);

        // Current balance is the chain tail: +200 +100 −50 −70 −40 +15 = 155.
        $this->assertEqualsWithDelta(155.00, $stats['current_balance'], 0.001);

        $this->assertSame(6, $stats['transaction_count']);
    }

    public function test_therapist_stats_returns_all_six_totals(): void
    {
        $therapist = User::factory()->therapist()->create();

        TherapistBill::factory()->state([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT->value,
            'subtotal' => 500.00,
            'adjustments_total' => 0,
            'total_due' => 500.00,
        ])->create();
        TherapistBill::factory()->state([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::PAID->value,
            'subtotal' => 200.00,
            'adjustments_total' => 0,
            'total_due' => 200.00,
        ])->create();

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::BILL_GENERATED,
            amount: 500.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::BILL_GENERATED,
            amount: 200.00,
            recordedAt: now()->subDays(4),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 300.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createCreditNoteForTherapist($therapist->id, 25.00, null, $this->admin->id, now()->subDay());
        $this->ledger->createRefundForTherapist($therapist->id, 10.00, null, $this->admin->id, now());

        $stats = $this->repo->getTherapistStats($therapist->id);

        $this->assertEqualsWithDelta(700.00, $stats['total_billed'], 0.001);
        $this->assertSame(2, $stats['bill_count']);

        $this->assertEqualsWithDelta(300.00, $stats['total_paid'], 0.001);
        $this->assertSame(1, $stats['payment_count']);

        $this->assertEqualsWithDelta(25.00, $stats['total_credit_notes'], 0.001);
        $this->assertSame(1, $stats['credit_note_count']);

        $this->assertEqualsWithDelta(10.00, $stats['total_refunds'], 0.001);
        $this->assertSame(1, $stats['refund_count']);

        $this->assertEqualsWithDelta(385.00, $stats['outstanding'], 0.001);

        // Chain tail: +500 +200 −300 −25 +10 = 385.
        $this->assertEqualsWithDelta(385.00, $stats['current_balance'], 0.001);

        $this->assertSame(5, $stats['transaction_count']);
    }

    public function test_school_stats_for_account_with_no_activity(): void
    {
        $school = School::factory()->create();

        $stats = $this->repo->getSchoolStats($school->id);

        $this->assertEqualsWithDelta(0.0, $stats['total_invoiced'], 0.001);
        $this->assertEqualsWithDelta(0.0, $stats['total_paid'], 0.001);
        $this->assertEqualsWithDelta(0.0, $stats['total_credit_notes'], 0.001);
        $this->assertEqualsWithDelta(0.0, $stats['total_refunds'], 0.001);
        $this->assertEqualsWithDelta(0.0, $stats['outstanding'], 0.001);
        $this->assertEqualsWithDelta(0.0, $stats['current_balance'], 0.001);
        $this->assertSame(0, $stats['invoice_count']);
        $this->assertSame(0, $stats['payment_count']);
        $this->assertSame(0, $stats['credit_note_count']);
        $this->assertSame(0, $stats['refund_count']);
        $this->assertSame(0, $stats['transaction_count']);
    }

    public function test_current_balance_uses_chain_tail_not_latest_created_at(): void
    {
        // Inserts a backdated row LAST (highest created_at, earliest recorded_at).
        // current_balance must reflect the chain tail (recorded_at desc), not the
        // most-recently-inserted row.
        $school = School::factory()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 100.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 30.00,
            recordedAt: now(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // Backdated credit note inserted now (highest created_at) but earliest recorded_at.
        $this->ledger->createCreditNoteForSchool($school->id, 20.00, null, $this->admin->id, now()->subDays(5));

        $stats = $this->repo->getSchoolStats($school->id);

        // Chain tail (recorded_at desc): payment row with balance_after = 100 − 20 − 30 = 50.
        $this->assertEqualsWithDelta(50.00, $stats['current_balance'], 0.001);
    }
}
