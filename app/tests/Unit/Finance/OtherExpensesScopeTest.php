<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\TherapistBillPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps expenses with no source', function (): void {
    Expense::factory()->create(['amount' => 100, 'source_type' => null, 'source_id' => null]);

    expect((float) Expense::excludingTherapistPayouts()->sum('amount'))->toBe(100.0);
});

it('excludes expenses sourced from a therapist bill payment', function (): void {
    Expense::factory()->create([
        'amount' => 480,
        'source_type' => TherapistBillPayment::class,
        'source_id' => 1,
    ]);

    expect((float) Expense::excludingTherapistPayouts()->sum('amount'))->toBe(0.0);
});

it('keeps expenses sourced from a non-payout model', function (): void {
    Expense::factory()->create([
        'amount' => 50,
        'source_type' => Invoice::class,
        'source_id' => 1,
    ]);

    expect((float) Expense::excludingTherapistPayouts()->sum('amount'))->toBe(50.0);
});

it('sums only non-payout expenses when mixed', function (): void {
    Expense::factory()->create(['amount' => 100, 'source_type' => null, 'source_id' => null]);
    Expense::factory()->create([
        'amount' => 480,
        'source_type' => TherapistBillPayment::class,
        'source_id' => 1,
    ]);

    expect((float) Expense::excludingTherapistPayouts()->sum('amount'))->toBe(100.0);
});
