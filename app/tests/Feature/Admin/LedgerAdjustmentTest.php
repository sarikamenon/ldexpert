<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Finance\Services\LedgerService;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LedgerAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_credit_note_for_school(): void
    {
        $school = School::factory()->create();

        // Seed an existing balance so we can verify subtraction.
        LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'seed',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 100.00,
                'notes' => 'Goodwill adjustment',
                'recorded_at' => now()->toDateString(),
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $entry = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $school->id)
            ->where('transaction_type', TransactionType::CREDIT_NOTE->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(100.00, (float) $entry->amount, 0.001);
        $this->assertEqualsWithDelta(400.00, (float) $entry->balance_after, 0.001);
        $this->assertNull($entry->reference_type);
        $this->assertNull($entry->reference_id);
        $this->assertSame('Goodwill adjustment', $entry->notes);
    }

    public function test_admin_can_create_refund_for_school(): void
    {
        $school = School::factory()->create();

        LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::CREDIT_NOTE,
            'amount' => 100.00,
            'balance_after' => -100.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'seed',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'refund',
                'amount' => 100.00,
                'notes' => null,
                'recorded_at' => now()->toDateString(),
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $entry = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $school->id)
            ->where('transaction_type', TransactionType::REFUND->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.00, (float) $entry->balance_after, 0.001);
    }

    public function test_admin_can_create_credit_note_for_therapist(): void
    {
        $therapist = User::factory()->therapist()->create();

        LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $therapist->id,
            'transaction_type' => TransactionType::BILL_GENERATED,
            'amount' => 300.00,
            'balance_after' => 300.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'seed',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'therapist', 'id' => $therapist->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 50.00,
                'recorded_at' => now()->toDateString(),
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $entry = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapist->id)
            ->where('transaction_type', TransactionType::CREDIT_NOTE->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(250.00, (float) $entry->balance_after, 0.001);
    }

    public function test_admin_can_create_refund_for_therapist(): void
    {
        $therapist = User::factory()->therapist()->create();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'therapist', 'id' => $therapist->id]),
            [
                'transaction_type' => 'refund',
                'amount' => 75.00,
                'recorded_at' => now()->toDateString(),
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $entry = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $therapist->id)
            ->where('transaction_type', TransactionType::REFUND->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(75.00, (float) $entry->balance_after, 0.001);
    }

    public function test_validation_fails_for_zero_amount(): void
    {
        $school = School::factory()->create();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 0,
            ]
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_fails_for_invalid_transaction_type(): void
    {
        $school = School::factory()->create();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'payment_received',
                'amount' => 100,
            ]
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['transaction_type']);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $therapist = User::factory()->therapist()->create();
        $school = School::factory()->create();

        $response = $this->actingAs($therapist)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 100,
            ]
        );

        // role middleware on the route group blocks non-admins before the form request runs.
        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_admin_can_edit_credit_note_amount_and_chain_recomputes(): void
    {
        $school = School::factory()->create();

        // Earlier invoice
        $invoice = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'recorded_at' => now()->subDays(2),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'invoice',
            'recorded_by_id' => $this->admin->id,
        ]);

        // Credit note (target of the edit)
        $creditNote = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::CREDIT_NOTE,
            'amount' => 100.00,
            'balance_after' => 400.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'goodwill',
            'recorded_by_id' => $this->admin->id,
        ]);

        // Later refund (must recompute after edit)
        $refund = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::REFUND,
            'amount' => 50.00,
            'balance_after' => 450.00,
            'recorded_at' => now(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'refund',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            route('admin.ledger.adjustment.update', ['entry' => $creditNote->id]),
            [
                'amount' => 200.00, // was 100
                'recorded_at' => $creditNote->recorded_at->toDateString(),
                'notes' => 'goodwill (revised)',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        // Invoice unchanged: +500
        $this->assertEqualsWithDelta(500.00, (float) $invoice->fresh()->balance_after, 0.001);
        // Credit note: +500 - 200 = 300
        $this->assertEqualsWithDelta(300.00, (float) $creditNote->fresh()->balance_after, 0.001);
        // Refund: 300 + 50 = 350
        $this->assertEqualsWithDelta(350.00, (float) $refund->fresh()->balance_after, 0.001);
    }

    public function test_admin_can_soft_delete_credit_note_and_chain_recomputes(): void
    {
        $school = School::factory()->create();

        $invoice = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'recorded_at' => now()->subDays(2),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'invoice',
            'recorded_by_id' => $this->admin->id,
        ]);

        $creditNote = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::CREDIT_NOTE,
            'amount' => 100.00,
            'balance_after' => 400.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'goodwill',
            'recorded_by_id' => $this->admin->id,
        ]);

        $refund = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::REFUND,
            'amount' => 50.00,
            'balance_after' => 450.00,
            'recorded_at' => now(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'refund',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            route('admin.ledger.adjustment.destroy', ['entry' => $creditNote->id])
        );

        $response->assertOk()->assertJson(['success' => true]);

        // Credit note row is soft-deleted
        $this->assertNotNull($creditNote->fresh()->deleted_at);

        // Invoice unchanged
        $this->assertEqualsWithDelta(500.00, (float) $invoice->fresh()->balance_after, 0.001);
        // Refund: 500 + 50 = 550 (credit note no longer contributes)
        $this->assertEqualsWithDelta(550.00, (float) $refund->fresh()->balance_after, 0.001);
    }

    public function test_cannot_edit_invoice_generated_row(): void
    {
        $school = School::factory()->create();

        $invoice = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'invoice',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            route('admin.ledger.adjustment.update', ['entry' => $invoice->id]),
            [
                'amount' => 999.00,
                'recorded_at' => now()->toDateString(),
            ]
        );

        $response->assertStatus(403);
    }

    public function test_cannot_delete_payment_received_row(): void
    {
        $school = School::factory()->create();

        $payment = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::PAYMENT_RECEIVED,
            'amount' => 100.00,
            'balance_after' => 0.00,
            'recorded_at' => now()->subDay(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'payment',
            'recorded_by_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            route('admin.ledger.adjustment.destroy', ['entry' => $payment->id])
        );

        $response->assertStatus(403);
    }

    public function test_validation_rejects_future_recorded_at_on_create(): void
    {
        $school = School::factory()->create();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 50.00,
                'recorded_at' => now()->addDay()->toDateString(),
            ]
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['recorded_at']);
    }

    public function test_backdated_credit_note_recomputes_chain(): void
    {
        $school = School::factory()->create();

        // Two existing entries; we'll insert a backdated credit note between them.
        $invoice = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 500.00,
            'balance_after' => 500.00,
            'recorded_at' => now()->subDays(3),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'invoice',
            'recorded_by_id' => $this->admin->id,
        ]);

        $refund = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::REFUND,
            'amount' => 50.00,
            'balance_after' => 550.00,
            'recorded_at' => now(),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'refund',
            'recorded_by_id' => $this->admin->id,
        ]);

        // Backdated credit note 2 days ago — between invoice and refund.
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
            [
                'transaction_type' => 'credit_note',
                'amount' => 100.00,
                'recorded_at' => now()->subDays(2)->toDateString(),
                'notes' => 'backdated',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $creditNote = LedgerEntry::where('ledgerable_type', School::class)
            ->where('ledgerable_id', $school->id)
            ->where('transaction_type', TransactionType::CREDIT_NOTE->value)
            ->first();

        // Invoice unchanged
        $this->assertEqualsWithDelta(500.00, (float) $invoice->fresh()->balance_after, 0.001);
        // Backdated credit note: 500 - 100 = 400
        $this->assertNotNull($creditNote);
        $this->assertEqualsWithDelta(400.00, (float) $creditNote->balance_after, 0.001);
        // Refund (later): 400 + 50 = 450
        $this->assertEqualsWithDelta(450.00, (float) $refund->fresh()->balance_after, 0.001);
    }

    public function test_ledger_verify_detects_per_row_drift_and_fix_heals_it(): void
    {
        $school = School::factory()->create();

        // Build a clean 3-row chain via the service so balance_after starts correct.
        /** @var LedgerService $service */
        $service = app(LedgerService::class);
        $service->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 200.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $service->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 50.00,
            recordedAt: now()->subDays(2),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $service->createEntry(
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

        // Manually corrupt the middle row only — final balance still nets out
        // to 100, so an end-of-chain-only check would falsely pass. The per-row
        // verifier must catch it.
        $rows = LedgerEntry::query()
            ->where('ledgerable_type', School::class)
            ->where('ledgerable_id', $school->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();
        /** @var LedgerEntry $middle */
        $middle = $rows[1];
        $middle->balance_after = '999.99';
        $middle->saveQuietly();

        $exitCode = Artisan::call('ledger:verify', [
            '--account-type' => School::class,
            '--account-id' => (string) $school->id,
        ]);

        $this->assertSame(1, $exitCode, 'verify should exit non-zero when a row drifts');
        $this->assertStringContainsString('DRIFT', Artisan::output());

        // --fix should heal the corrupted row.
        $fixExit = Artisan::call('ledger:verify', [
            '--account-type' => School::class,
            '--account-id' => (string) $school->id,
            '--fix' => true,
        ]);

        $this->assertSame(0, $fixExit, 'verify --fix should exit zero after repair');

        $refreshed = LedgerEntry::find($middle->id);
        $this->assertNotNull($refreshed);
        $this->assertEqualsWithDelta(150.00, (float) $refreshed->balance_after, 0.001);

        // Verifier should now report clean.
        $reverifyExit = Artisan::call('ledger:verify', [
            '--account-type' => School::class,
            '--account-id' => (string) $school->id,
        ]);
        $this->assertSame(0, $reverifyExit);
    }

    public function test_create_adjustment_stamps_recorded_at_with_current_time_of_day(): void
    {
        $school = School::factory()->create();

        // Freeze time to a recognisable moment so we can assert the time portion.
        $frozen = Carbon::parse('2026-06-15 14:37:22');
        Carbon::setTestNow($frozen);

        try {
            $response = $this->actingAs($this->admin)->postJson(
                route('admin.ledger.accounts.adjustment.store', ['type' => 'school', 'id' => $school->id]),
                [
                    'transaction_type' => 'credit_note',
                    'amount' => 25.00,
                    'recorded_at' => $frozen->toDateString(),
                ]
            );
            $response->assertOk();

            $entry = LedgerEntry::where('ledgerable_type', School::class)
                ->where('ledgerable_id', $school->id)
                ->where('transaction_type', TransactionType::CREDIT_NOTE->value)
                ->first();

            $this->assertNotNull($entry);
            // Date matches the picked date; time matches "now" — not 00:00:00.
            $this->assertSame('2026-06-15', $entry->recorded_at->toDateString());
            $this->assertSame(14, $entry->recorded_at->hour);
            $this->assertSame(37, $entry->recorded_at->minute);
            $this->assertSame(22, $entry->recorded_at->second);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_recompute_chain_from_heals_drift_on_rows_earlier_than_from(): void
    {
        $school = School::factory()->create();

        /** @var LedgerService $service */
        $service = app(LedgerService::class);

        $first = $service->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::INVOICE_GENERATED,
            amount: 100.00,
            recordedAt: now()->subDays(5),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );
        $second = $service->createEntry(
            ledgerableType: School::class,
            ledgerableId: $school->id,
            type: TransactionType::PAYMENT_RECEIVED,
            amount: 40.00,
            recordedAt: now()->subDays(3),
            referenceType: null,
            referenceId: null,
            notes: null,
            recordedById: $this->admin->id,
        );

        // Corrupt the *earlier* row. Then call recomputeChainFrom with $from set
        // to a moment AFTER the corrupted row — the old behavior would have
        // skipped it; the self-healing version must rewrite it anyway.
        $first->balance_after = '9999.99';
        $first->saveQuietly();

        $service->recomputeChainFrom(School::class, $school->id, now()->subDay());

        $firstRefreshed = LedgerEntry::find($first->id);
        $secondRefreshed = LedgerEntry::find($second->id);
        $this->assertNotNull($firstRefreshed);
        $this->assertNotNull($secondRefreshed);
        $this->assertEqualsWithDelta(100.00, (float) $firstRefreshed->balance_after, 0.001);
        $this->assertEqualsWithDelta(60.00, (float) $secondRefreshed->balance_after, 0.001);
    }
}
