<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Services\AdvanceReconciliationService;
use App\Enums\BillingMode;
use App\Models\BillingSchedule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ReconcileAdvanceInvoices extends Command
{
    protected $signature = 'billing:reconcile-advance
        {--schedule= : Run a specific billing schedule by ID}
        {--dry-run : Show what would be reconciled without creating anything}';

    protected $description = 'Reconcile the prior calendar month for advance schedules, catching late-approved session logs (10th of month).';

    public function __construct(
        private readonly AdvanceReconciliationService $reconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scheduleId = $this->option('schedule');
        $referenceDate = now();

        if ($dryRun) {
            $this->warn('DRY RUN — no invoices or credit notes will be created.');
            $this->newLine();
        }

        $schedules = $this->resolveSchedules($scheduleId !== null ? (int) $scheduleId : null);

        if ($schedules->isEmpty()) {
            $this->info('No active advance schedules to reconcile.');

            return self::SUCCESS;
        }

        $results = [];
        foreach ($schedules as $schedule) {
            try {
                $results[] = $this->reconciliationService->reconcileSchedule($schedule, $referenceDate, $dryRun);
            } catch (\Throwable $e) {
                Log::error('Advance reconciliation failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Schedule #{$schedule->id}: {$e->getMessage()}");
            }
        }

        $this->displayResults($results);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, BillingSchedule>
     */
    private function resolveSchedules(?int $scheduleId): Collection
    {
        $query = BillingSchedule::query()
            ->active()
            ->forSchools()
            ->where('billing_mode', BillingMode::ADVANCE->value);

        if ($scheduleId !== null) {
            $query->where('id', $scheduleId);
        }

        /** @var Collection<int, BillingSchedule> $schedules */
        $schedules = $query->get();

        return $schedules;
    }

    /**
     * @param  array<int, array{schedule_id: int, status: string, period_start: string, period_end: string, net_amount: float, settlement_invoice_id: ?int, credit_note_ledger_entry_id: ?int, lines: int}>  $results
     */
    private function displayResults(array $results): void
    {
        if ($results === []) {
            return;
        }

        $rows = array_map(static fn (array $r): array => [
            $r['schedule_id'],
            "{$r['period_start']} to {$r['period_end']}",
            $r['status'],
            $r['lines'],
            '$'.number_format($r['net_amount'], 2),
            $r['settlement_invoice_id'] ?? '—',
            $r['credit_note_ledger_entry_id'] ?? '—',
        ], $results);

        $this->table(
            ['Schedule', 'Period', 'Status', 'Lines', 'Net', 'Settlement Inv', 'Credit Note'],
            $rows,
        );
    }
}
