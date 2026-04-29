<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
