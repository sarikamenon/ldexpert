<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Services\ExpenseService;
use App\DTOs\CreateExpenseDTO;
use App\Models\Expense;
use App\Models\TherapistBillPayment;
use Illuminate\Console\Command;

class BackfillTherapistPayoutExpenses extends Command
{
    protected $signature = 'expenses:backfill-therapist-payouts
        {--dry-run : Report what would be created without writing anything}';

    protected $description = 'Create matching Expense rows for existing therapist bill payments that predate the auto-linkage feature.';

    public function __construct(
        private readonly ExpenseService $expenses,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $categoryId = (int) config('expenses.protected_categories.therapist-payouts');

        if ($categoryId === 0) {
            $this->error('Config key expenses.protected_categories.therapist-payouts is not set or is 0.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no expense rows will be created.');
            $this->newLine();
        }

        $payments = TherapistBillPayment::with(['therapistBill.therapist'])->get();
        $total = $payments->count();

        if ($total === 0) {
            $this->info('No therapist bill payments found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Scanning {$total} therapist bill payments...");

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $createdAmount = 0.0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($payments as $payment) {
            if (Expense::forSource($payment)->exists()) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $bill = $payment->therapistBill;
            $therapist = $bill?->therapist;

            if ($bill === null || $therapist === null) {
                $failed++;
                $this->newLine();
                $this->warn("Skipping payment #{$payment->id}: missing bill or therapist.");
                $bar->advance();

                continue;
            }

            $dto = new CreateExpenseDTO(
                expenseCategoryId: $categoryId,
                expenseDate: $payment->paid_at->format('Y-m-d'),
                amount: (float) $payment->amount,
                vendorPayee: $therapist->name,
                description: "Payment for therapist bill #{$bill->id}",
                reference: $payment->reference,
                createdById: $payment->recorded_by_id,
            );

            if (! $dryRun) {
                try {
                    $this->expenses->createExpenseFromSource($dto, $payment);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed payment #{$payment->id}: {$e->getMessage()}");
                    $bar->advance();

                    continue;
                }
            }

            $created++;
            $createdAmount += (float) $payment->amount;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'Would create' : 'Created';
        $this->info(sprintf(
            '%s %d expense(s) totaling $%s. Skipped %d (already linked). Failed %d.',
            $verb,
            $created,
            number_format($createdAmount, 2),
            $skipped,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
