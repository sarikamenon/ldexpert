<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Finance\Services\LedgerService;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllTransactionsDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->ledger = app(LedgerService::class);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_admin_can_access_all_transactions_data_endpoint(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 0, 'dir' => 'desc']],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_non_admin_cannot_access_all_transactions_data_endpoint(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 0, 'dir' => 'desc']],
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('admin.ledger.accounts.all-transactions.data'), [])
            ->assertUnauthorized();
    }

    // -----------------------------------------------------------------
    // Default behaviour (no direction filter): pure accruals excluded,
    // credit_note and refund included as income/expense respectively
    // -----------------------------------------------------------------

    public function test_without_direction_filter_only_cash_types_are_returned(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();
        $businessId = (int) config('finance.business_account_user_id', 1);

        // Pure accruals — must NOT appear
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 500.00,
            recordedAt: now()->subDays(6),
            referenceType: null,
            referenceId: null,
            notes: 'accrual-seed',
            recordedById: $this->admin->id,
        );

        // Cash + adjustment types — must appear
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 200.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: 'payment-seed',
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 150.00,
            recordedAt: now()->subDays(4),
            referenceType: null,
            referenceId: null,
            notes: 'bill-payment-seed',
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $businessId,
            type: TransactionType::EXPENSE,
            amount: 75.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: 'expense-seed',
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::CREDIT_NOTE,
            amount: 50.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: 'credit-note-seed',
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::REFUND,
            amount: 30.00,
            recordedAt: now()->subDays(1),
            referenceType: null,
            referenceId: null,
            notes: 'refund-seed',
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
            ]);

        $response->assertOk();

        // 5 entries (payment_received, payment_made, expense, credit_note, refund); invoice_generated excluded
        $this->assertSame(5, $response->json('recordsTotal'));
    }

    // -----------------------------------------------------------------
    // Direction filter: income
    // -----------------------------------------------------------------

    public function test_income_direction_filter_returns_payment_received_and_credit_note_rows(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();
        $businessId = (int) config('finance.business_account_user_id', 1);

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 300.00,
            recordedAt: now()->subDays(4),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::CREDIT_NOTE,
            amount: 50.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 100.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $businessId,
            type: TransactionType::EXPENSE,
            amount: 50.00,
            recordedAt: now()->subDay(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_direction' => 'income',
            ]);

        $response->assertOk();
        // payment_received + credit_note; payment_made and expense excluded
        $this->assertSame(2, $response->json('recordsTotal'));
    }

    // -----------------------------------------------------------------
    // Direction filter: expense
    // -----------------------------------------------------------------

    public function test_expense_direction_filter_returns_payment_made_expense_and_refund_rows(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();
        $businessId = (int) config('finance.business_account_user_id', 1);

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 300.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 100.00,
            recordedAt: now()->subDays(4),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $businessId,
            type: TransactionType::EXPENSE,
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
            type: TransactionType::REFUND,
            amount: 25.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_direction' => 'expense',
            ]);

        $response->assertOk();
        // payment_made + expense + refund; payment_received excluded
        $this->assertSame(3, $response->json('recordsTotal'));
    }

    // -----------------------------------------------------------------
    // Date filters
    // -----------------------------------------------------------------

    public function test_date_from_filter_excludes_entries_before_that_date(): void
    {
        $school = School::factory()->create();

        // Entry 10 days ago — should be excluded
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 100.00,
            recordedAt: now()->subDays(10),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // Entry 2 days ago — should be included
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 200.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_date_from' => now()->subDays(5)->toDateString(),
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
    }

    public function test_date_to_filter_excludes_entries_after_that_date(): void
    {
        $school = School::factory()->create();

        // Entry 10 days ago — should be included
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 100.00,
            recordedAt: now()->subDays(10),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // Entry today — should be excluded
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 200.00,
            recordedAt: now(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_date_to' => now()->subDays(5)->toDateString(),
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
    }

    // -----------------------------------------------------------------
    // Account filters
    // -----------------------------------------------------------------

    public function test_school_id_filter_returns_only_that_schools_entries(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $schoolA->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 100.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $schoolB->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 200.00,
            recordedAt: now()->subDay(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_school_id' => $schoolA->id,
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
    }

    public function test_therapist_id_filter_returns_only_that_therapists_entries(): void
    {
        $therapistA = User::factory()->therapist()->create();
        $therapistB = User::factory()->therapist()->create();

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapistA->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 100.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapistB->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 200.00,
            recordedAt: now()->subDay(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 25,
                'order' => [['column' => 0, 'dir' => 'desc']],
                'filter_therapist_id' => $therapistA->id,
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
    }

    // -----------------------------------------------------------------
    // Response shape
    // -----------------------------------------------------------------

    public function test_response_contains_required_datatables_keys(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 3,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 0, 'dir' => 'desc']],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->assertSame(3, $response->json('draw'));
    }

    public function test_empty_result_when_no_cash_entries_exist(): void
    {
        $school = School::factory()->create();

        // Only an accrual entry — should be excluded
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 500.00,
            recordedAt: now(),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.ledger.accounts.all-transactions.data'), [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'order' => [['column' => 0, 'dir' => 'desc']],
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('recordsTotal'));
        $this->assertSame([], $response->json('data'));
    }

    // -----------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------

    public function test_export_returns_csv_with_header_row(): void
    {
        $school = School::factory()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 100.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: 'export-test',
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ledger.accounts.all-transactions.export', [
                'filter_date_from' => now()->subDays(5)->format('Y-m-d'),
                'filter_date_to'   => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $lines = explode("\n", trim($response->streamedContent()));
        $this->assertCount(2, $lines); // header + 1 data row
        $this->assertStringContainsString('Date', $lines[0]);
        $this->assertStringContainsString('export-test', $lines[1]);
    }

    public function test_export_respects_date_filter(): void
    {
        $school = School::factory()->create();

        // Entry inside range
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 200.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: 'in-range',
            recordedById: $this->admin->id,
        );

        // Entry outside range
        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 500.00,
            recordedAt: now()->subDays(60),
            referenceType: null,
            referenceId: null,
            notes: 'out-of-range',
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ledger.accounts.all-transactions.export', [
                'filter_date_from' => now()->subDays(7)->format('Y-m-d'),
                'filter_date_to'   => now()->format('Y-m-d'),
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('in-range', $content);
        $this->assertStringNotContainsString('out-of-range', $content);
    }

    public function test_export_respects_direction_filter(): void
    {
        $school = School::factory()->create();
        $therapist = User::factory()->therapist()->create();

        $this->ledger->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 300.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: 'income-entry',
            recordedById: $this->admin->id,
        );

        $this->ledger->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapist->id,
            type: TransactionType::PAYMENT_MADE,
            amount: 150.00,
            recordedAt: now()->subDays(1),
            referenceType: null,
            referenceId: null,
            notes: 'expense-entry',
            recordedById: $this->admin->id,
        );

        $response = $this->actingAs($this->admin)
            ->get(route('admin.ledger.accounts.all-transactions.export', [
                'filter_date_from'  => now()->subDays(7)->format('Y-m-d'),
                'filter_date_to'    => now()->format('Y-m-d'),
                'filter_direction'  => 'income',
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('income-entry', $content);
        $this->assertStringNotContainsString('expense-entry', $content);
    }
}
