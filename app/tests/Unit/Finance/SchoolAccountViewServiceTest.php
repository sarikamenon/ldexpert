<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Finance\Services\SchoolAccountViewService;
use App\Enums\SessionLogStatus;
use App\Enums\TherapistBillStatus;
use App\Enums\TransactionType;
use App\Models\School;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    /**
     * Wide enough window that any factory-created session_log or ledger entry
     * lands inside it, so existing test scenarios don't have to think about
     * the windowing contract.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function loadAll(School $school): Collection
    {
        $from = CarbonImmutable::now()->subYears(10);
        $to = CarbonImmutable::now()->addYears(1);

        return $this->service->getTransactions($school, $from, $to);
    }

    public function test_includes_approved_billable_session_logs_regardless_of_bill_status(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        // Approved + unbilled.
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'therapist_bill_id' => null,
            'school_invoice_amount' => 100.00,
        ]);

        // Approved + on a sent (unpaid) bill.
        $sentBill = TherapistBill::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => TherapistBillStatus::SENT->value,
        ]);
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'therapist_bill_id' => $sentBill->id,
            'school_invoice_amount' => 150.00,
        ]);

        // Draft (not approved) — must be excluded.
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::DRAFT->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 200.00,
        ]);

        // Approved but non-billable to school — must be excluded.
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => false,
            'school_invoice_amount' => 300.00,
        ]);

        $charges = $this->loadAll($school)->where('type', 'charge');

        $this->assertCount(2, $charges);
        $this->assertEqualsWithDelta(250.00, $charges->sum('debit'), 0.001);
    }

    public function test_multi_school_session_logs_are_attributed_per_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->create([
            'school_id' => $schoolA->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 100.00,
        ]);
        SessionLog::factory()->create([
            'school_id' => $schoolB->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 250.00,
        ]);

        $rowsA = $this->loadAll($schoolA)->where('type', 'charge');
        $rowsB = $this->loadAll($schoolB)->where('type', 'charge');

        $this->assertCount(1, $rowsA);
        $this->assertEqualsWithDelta(100.00, (float) $rowsA->first()['debit'], 0.001);
        $this->assertCount(1, $rowsB);
        $this->assertEqualsWithDelta(250.00, (float) $rowsB->first()['debit'], 0.001);
    }

    public function test_merges_payments_credit_notes_and_refunds_excluding_invoice_generated(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
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

        $rows = $this->loadAll($school);

        // Should not include INVOICE_GENERATED rows.
        $this->assertCount(0, $rows->where('type', TransactionType::INVOICE_GENERATED->value));

        $this->assertCount(1, $rows->where('type', 'charge'));
        $this->assertCount(1, $rows->where('type', TransactionType::PAYMENT_RECEIVED->value));
        $this->assertCount(1, $rows->where('type', TransactionType::CREDIT_NOTE->value));
        $this->assertCount(1, $rows->where('type', TransactionType::REFUND->value));

        // Running balance: charge +200, payment -50, credit_note -30, refund +10 = 130.
        $this->assertEqualsWithDelta(130.00, $this->service->getSummary($school)['net_balance'], 0.001);
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

        $rows = $this->loadAll($school);

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

        $rows = $this->loadAll($school);

        $this->assertCount(0, $rows);
        $this->assertSame(0.0, $this->service->getSummary($school)['net_balance']);
    }

    public function test_get_summary_aggregates_all_time_totals(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 100.00,
        ]);
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 75.00,
        ]);

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 50.00,
            recordedAt: now()->subDay(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $this->ledger->createCreditNoteForSchool($school->id, 20.00, null, $this->admin->id, now()->subHours(2));
        $this->ledger->createRefundForSchool($school->id, 10.00, null, $this->admin->id, now());

        $summary = $this->service->getSummary($school);

        $this->assertEqualsWithDelta(175.00, $summary['total_charges'], 0.001);
        $this->assertEqualsWithDelta(50.00, $summary['total_paid'], 0.001);
        $this->assertEqualsWithDelta(20.00, $summary['total_credit_notes'], 0.001);
        $this->assertEqualsWithDelta(10.00, $summary['total_refunds'], 0.001);
        $this->assertEqualsWithDelta(0.00, $summary['total_invoiced'], 0.001);
        // Net: +175 (charges) -50 (payment) -20 (credit) +10 (refund) = 115.
        $this->assertEqualsWithDelta(115.00, $summary['net_balance'], 0.001);
    }

    public function test_get_summary_includes_total_invoiced_from_invoice_generated_entries(): void
    {
        $school = School::factory()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 600.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $summary = $this->service->getSummary($school);

        $this->assertEqualsWithDelta(600.00, $summary['total_invoiced'], 0.001);
    }

    public function test_window_excludes_rows_outside_range_but_includes_them_in_opening_balance(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        // Old charge — outside window, should fold into opening balance.
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 200.00,
            'session_date' => CarbonImmutable::now()->subDays(60)->format('Y-m-d'),
            'start_time' => CarbonImmutable::now()->subDays(60),
        ]);

        // Recent charge — inside window.
        SessionLog::factory()->create([
            'school_id' => $school->id,
            'therapist_id' => $therapist->id,
            'status' => SessionLogStatus::APPROVED->value,
            'is_billable_school' => true,
            'school_invoice_amount' => 50.00,
            'session_date' => CarbonImmutable::now()->subDays(5)->format('Y-m-d'),
            'start_time' => CarbonImmutable::now()->subDays(5),
        ]);

        $from = CarbonImmutable::now()->subDays(30);
        $to = CarbonImmutable::now();

        $rows = $this->service->getTransactions($school, $from, $to);

        // Only the recent charge appears in the table.
        $this->assertCount(1, $rows);
        $first = $rows->first();
        $this->assertNotNull($first);
        // balance_after reflects the older charge baked into the opening balance.
        $this->assertEqualsWithDelta(250.00, (float) $first['balance_after'], 0.001);
    }

    public function test_default_window_is_30_days_in_school_timezone(): void
    {
        $school = School::factory()->create(['timezone' => 'America/Los_Angeles']);

        [$from, $to] = $this->service->defaultWindow($school);

        $this->assertSame(SchoolAccountViewService::DEFAULT_WINDOW_DAYS - 1, (int) $from->diffInDays($to));
    }
}
