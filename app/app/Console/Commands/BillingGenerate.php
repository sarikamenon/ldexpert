<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Services\BillingAutomationService;
use App\Domain\Billing\Services\BillingScheduleService;
use App\DTOs\BillingRunResultDTO;
use App\Enums\BillingScheduleRunStatus;
use Illuminate\Console\Command;

class BillingGenerate extends Command
{
    protected $signature = 'billing:generate
        {--type=all : Schedule type to process (school_invoice|private_student_invoice|therapist_bill|all)}
        {--schedule= : Run a specific schedule by ID}
        {--dry-run : Show what would be generated without creating}';

    protected $description = 'Generate invoices and therapist bills from billing schedules';

    public function __construct(
        private readonly BillingAutomationService $automationService,
        private readonly BillingScheduleService $scheduleService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $scheduleId = $this->option('schedule');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no invoices or bills will be created.');
            $this->newLine();
        }

        if ($scheduleId !== null) {
            return $this->processSingle((int) $scheduleId, $dryRun);
        }

        return $this->processAll($type, $dryRun);
    }

    private function processAll(string $type, bool $dryRun): int
    {
        $this->info("Processing all due schedules (type: {$type})...");

        $results = $this->automationService->processAllDueSchedules($type, $dryRun);

        if ($results->isEmpty()) {
            $this->info('No due schedules found.');

            return self::SUCCESS;
        }

        $this->displayResults($results);

        return self::SUCCESS;
    }

    private function processSingle(int $scheduleId, bool $dryRun): int
    {
        $schedule = $this->scheduleService->find($scheduleId);

        if ($schedule === null) {
            $this->error("Billing schedule #{$scheduleId} not found.");

            return self::FAILURE;
        }

        if (! $schedule->is_active) {
            $this->warn("Schedule #{$scheduleId} is inactive. Processing anyway...");
        }

        $this->info("Processing schedule #{$scheduleId} ({$schedule->schedule_type->label()})...");

        try {
            $result = $this->automationService->processSingleSchedule($schedule, $dryRun);
            $this->displayResults(collect([$result]));
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BillingRunResultDTO>  $results
     */
    private function displayResults(\Illuminate\Support\Collection $results): void
    {
        $rows = $results->map(fn (BillingRunResultDTO $r): array => [
            $r->billingScheduleId,
            "{$r->billingPeriodStart} to {$r->billingPeriodEnd}",
            $r->status,
            $r->sessionsFound,
            $r->sessionsFromPriorPeriods,
            $r->adjustmentsCount,
            $r->totalAmount !== null ? '$'.number_format($r->totalAmount, 2) : '—',
            $r->invoiceId ?? $r->therapistBillId ?? '—',
        ]);

        $this->table(
            ['Schedule', 'Period', 'Status', 'Sessions', 'Late', 'Adjustments', 'Total', 'Doc ID'],
            $rows->all(),
        );

        $successful = $results->filter(fn (BillingRunResultDTO $r): bool => $r->status === BillingScheduleRunStatus::SUCCESS->value)->count();
        $skipped = $results->filter(fn (BillingRunResultDTO $r): bool => $r->status === BillingScheduleRunStatus::SKIPPED_NO_SESSIONS->value)->count();
        $failed = $results->filter(fn (BillingRunResultDTO $r): bool => $r->status === BillingScheduleRunStatus::FAILED->value)->count();

        $this->newLine();
        $this->info("Done: {$successful} generated, {$skipped} skipped, {$failed} failed.");

        if ($failed > 0) {
            $this->newLine();
            $results->filter(fn (BillingRunResultDTO $r): bool => $r->status === BillingScheduleRunStatus::FAILED->value)
                ->each(fn (BillingRunResultDTO $r) => $this->error("  Schedule #{$r->billingScheduleId}: {$r->errorMessage}"));
        }
    }
}
