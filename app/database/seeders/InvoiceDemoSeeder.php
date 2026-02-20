<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\Services\LedgerService;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceDemoSeeder extends Seeder
{
    private const DEMO_NOTE_MARKER = '[demo]';

    /**
     * Seed demo invoices for a subset of schools.
     */
    public function run(): void
    {
        // Run demo invoices in all non-production environments.
        if (app()->environment('production')) {
            return;
        }

        // If we've already created demo invoices, don't create them again.
        if (Invoice::where('notes', 'like', '%'.self::DEMO_NOTE_MARKER.'%')->exists()) {
            return;
        }

        /** @var User|null $admin */
        $admin = User::query()->orderBy('id')->first();

        /** @var LedgerService $ledgerService */
        $ledgerService = app(LedgerService::class);

        // Use existing schools if available; fall back to a few factory schools.
        $schools = School::query()->orderBy('id')->limit(5)->get();

        if ($schools->isEmpty()) {
            $schools = School::factory()->count(3)->create();
        }

        foreach ($schools as $index => $school) {
            // For each school, create a mix of draft, sent, and paid invoices.
            $this->createInvoicesForSchool($school, $admin, $ledgerService, $index + 1);
        }
    }

    private function createInvoicesForSchool(
        School $school,
        ?User $admin,
        LedgerService $ledgerService,
        int $schoolIndex,
    ): void {
        // 2 draft, 2 sent, 2 paid per school (medium volume overall)
        $counts = [
            InvoiceStatus::DRAFT->value => 2,
            InvoiceStatus::SENT->value => 2,
            InvoiceStatus::PAID->value => 2,
        ];

        foreach ($counts as $statusValue => $count) {
            for ($i = 1; $i <= $count; $i++) {
                /** @var Invoice $invoice */
                $invoice = Invoice::factory()
                    ->for($school)
                    ->state([
                        // Use factory-generated invoice_number to avoid unique constraint collisions.
                        'status' => $statusValue,
                        'sent_by_id' => $statusValue !== InvoiceStatus::DRAFT->value ? $admin?->id : null,
                        'sent_at' => $statusValue !== InvoiceStatus::DRAFT->value ? now() : null,
                        'notes' => trim('Finance module demo invoice '.self::DEMO_NOTE_MARKER),
                    ])
                    ->create();

                // Ensure ledger reflects invoice generation for this school.
                $ledgerService->createInvoiceGeneratedEntry($invoice);
            }
        }
    }
}
