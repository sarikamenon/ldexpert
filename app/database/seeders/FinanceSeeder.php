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
        // Run only in local/staging so finance demo data is available there;
        // skip in production and testing.
        if (app()->environment(['production', 'testing'])) {
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
