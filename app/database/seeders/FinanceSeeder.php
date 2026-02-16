<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    /**
     * Seed finance-related demo data for local/staging.
     */
    public function run(): void
    {
        // Run in all non-production environments so finance demo data
        // is available in any local/dev/staging context.
        if (app()->environment('production')) {
            return;
        }

        // Avoid duplicating demo data on repeated runs.
        // Individual demo seeders are responsible for their own idempotency.
        $this->call([
            InvoiceDemoSeeder::class,
            TherapistBillDemoSeeder::class,
            ExpenseDemoSeeder::class,
            PaymentDemoSeeder::class,
        ]);
    }
}

