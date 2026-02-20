<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_seeder_runs_only_in_local_or_staging(): void
    {
        // By default tests run in the testing environment, so FinanceSeeder should early-return.
        $this->seed(\Database\Seeders\FinanceSeeder::class);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('therapist_bills', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('invoice_payments', 0);
        $this->assertDatabaseCount('therapist_bill_payments', 0);
    }

    public function test_demo_seeders_create_related_finance_data_when_forced(): void
    {
        // Ensure core domain seed data exists (schools, therapists, etc.).
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        // Manually run individual demo seeders to verify relationships without relying on environment.
        $this->seed(\Database\Seeders\InvoiceDemoSeeder::class);
        $this->seed(\Database\Seeders\TherapistBillDemoSeeder::class);
        $this->seed(\Database\Seeders\ExpenseDemoSeeder::class);
        $this->seed(\Database\Seeders\PaymentDemoSeeder::class);

        $this->assertGreaterThan(0, Invoice::count(), 'Invoices should be seeded');
        $this->assertGreaterThan(0, TherapistBill::count(), 'Therapist bills should be seeded');

        $this->assertGreaterThan(
            0,
            LedgerEntry::where('reference_type', Invoice::class)->count(),
            'Invoice ledger entries should be seeded',
        );

        $this->assertGreaterThan(
            0,
            LedgerEntry::where('reference_type', TherapistBill::class)->count(),
            'Therapist bill ledger entries should be seeded',
        );

        $this->assertGreaterThan(
            0,
            LedgerEntry::whereIn('reference_type', [InvoicePayment::class, TherapistBillPayment::class])->count(),
            'Payment ledger entries should be seeded',
        );
    }
}
