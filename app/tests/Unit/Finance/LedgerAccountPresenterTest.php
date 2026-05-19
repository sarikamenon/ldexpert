<?php

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Domain\Finance\Support\LedgerAccountPresenter;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LedgerAccountPresenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    // -----------------------------------------------------------------
    // displayName()
    // -----------------------------------------------------------------

    public function test_display_name_returns_school_display_name(): void
    {
        $school = School::factory()->create(['display_name' => 'Sunrise Academy']);

        $entry = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 100.00,
            'balance_after' => 100.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $entry->load('ledgerable');

        $this->assertSame('Sunrise Academy', LedgerAccountPresenter::displayName($entry));
    }

    public function test_display_name_uses_full_name_when_display_name_attribute_is_forced_null(): void
    {
        $school = School::factory()->create(['full_name' => 'Sunrise Academy Full']);

        $entry = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 100.00,
            'balance_after' => 100.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        // Simulate a null display_name in-memory without hitting the NOT NULL DB constraint.
        $school->setAttribute('display_name', null);
        $entry->setRelation('ledgerable', $school);

        $this->assertSame('Sunrise Academy Full', LedgerAccountPresenter::displayName($entry));
    }

    public function test_display_name_returns_operating_expenses_for_business_account(): void
    {
        // The business account ID comes from config. Override config so it matches
        // the admin user created in setUp, avoiding a duplicate-PK conflict.
        $businessId = $this->admin->id;
        config(['finance.business_account_user_id' => $businessId]);

        $entry = LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $businessId,
            'transaction_type' => TransactionType::EXPENSE,
            'amount' => 50.00,
            'balance_after' => -50.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $entry->load('ledgerable');

        $this->assertSame('Operating Expenses', LedgerAccountPresenter::displayName($entry));
    }

    public function test_display_name_returns_therapist_user_name(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Jane Smith']);

        $entry = LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $therapist->id,
            'transaction_type' => TransactionType::PAYMENT_MADE,
            'amount' => 200.00,
            'balance_after' => -200.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $entry->load('ledgerable');

        $this->assertSame('Jane Smith', LedgerAccountPresenter::displayName($entry));
    }

    // -----------------------------------------------------------------
    // accountType()
    // -----------------------------------------------------------------

    public function test_account_type_returns_school_for_school_ledgerable(): void
    {
        $school = School::factory()->create();

        $entry = LedgerEntry::create([
            'ledgerable_type' => School::class,
            'ledgerable_id' => $school->id,
            'transaction_type' => TransactionType::INVOICE_GENERATED,
            'amount' => 100.00,
            'balance_after' => 100.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->assertSame('School', LedgerAccountPresenter::accountType($entry));
    }

    public function test_account_type_returns_business_for_business_account(): void
    {
        $businessId = $this->admin->id;
        config(['finance.business_account_user_id' => $businessId]);

        $entry = LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $businessId,
            'transaction_type' => TransactionType::EXPENSE,
            'amount' => 50.00,
            'balance_after' => -50.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->assertSame('Business', LedgerAccountPresenter::accountType($entry));
    }

    public function test_account_type_returns_therapist_for_non_business_user(): void
    {
        $therapist = User::factory()->therapist()->create();

        $entry = LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $therapist->id,
            'transaction_type' => TransactionType::PAYMENT_MADE,
            'amount' => 200.00,
            'balance_after' => -200.00,
            'recorded_at' => now(),
            'recorded_by_id' => $this->admin->id,
        ]);

        $this->assertSame('Therapist', LedgerAccountPresenter::accountType($entry));
    }
}
