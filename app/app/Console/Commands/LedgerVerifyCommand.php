<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Services\LedgerService;
use App\Models\LedgerEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LedgerVerifyCommand extends Command
{
    protected $signature = 'ledger:verify
        {--account-type= : Limit to a single ledgerable_type (FQCN, e.g. App\\Models\\School)}
        {--account-id= : Limit to a single ledgerable_id (requires --account-type)}
        {--fix : Recompute the chain for any account whose balance_after drifts from SUM(signed_amount)}';

    protected $description = 'Verify the ledger invariant: MAX(id).balance_after == SUM(signedAmount) per account. Reports drift; with --fix, recomputes affected accounts.';

    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $accountType = $this->option('account-type');
        $accountId = $this->option('account-id');
        $shouldFix = (bool) $this->option('fix');

        $query = LedgerEntry::query()
            ->select('ledgerable_type', 'ledgerable_id')
            ->groupBy('ledgerable_type', 'ledgerable_id');

        if ($accountType !== null) {
            $query->where('ledgerable_type', $accountType);
        }
        if ($accountId !== null) {
            $query->where('ledgerable_id', (int) $accountId);
        }

        /** @var array<int, array{ledgerable_type: string, ledgerable_id: int}> $accounts */
        $accounts = $query->get()->map(static fn ($row): array => [
            'ledgerable_type' => (string) $row->ledgerable_type,
            'ledgerable_id' => (int) $row->ledgerable_id,
        ])->all();

        if ($accounts === []) {
            $this->info('No ledger accounts found.');

            return self::SUCCESS;
        }

        $driftCount = 0;
        $fixedCount = 0;

        foreach ($accounts as $account) {
            $type = $account['ledgerable_type'];
            $id = $account['ledgerable_id'];

            $rows = LedgerEntry::query()
                ->where('ledgerable_type', $type)
                ->where('ledgerable_id', $id)
                ->orderBy('recorded_at')
                ->orderBy('id')
                ->get(['id', 'transaction_type', 'amount', 'balance_after']);

            $running = 0.0;
            $firstDrift = null;
            foreach ($rows as $row) {
                $running += $row->signedAmount();
                if ($firstDrift === null && ! $this->approximatelyEqual($running, (float) $row->balance_after)) {
                    $firstDrift = [
                        'id' => (int) $row->id,
                        'expected' => $running,
                        'stored' => (float) $row->balance_after,
                    ];
                }
            }

            if ($firstDrift !== null) {
                $driftCount++;
                $this->warn(sprintf(
                    'DRIFT: %s#%d — row #%d expected %.2f, stored %.2f (delta %.2f)',
                    $type,
                    $id,
                    $firstDrift['id'],
                    $firstDrift['expected'],
                    $firstDrift['stored'],
                    $firstDrift['stored'] - $firstDrift['expected'],
                ));

                if ($shouldFix) {
                    // @phpstan-ignore argument.type ($type is a class-string read from the ledger row)
                    $this->ledgerService->recomputeChainFrom($type, $id, Carbon::createFromTimestamp(0));
                    $fixedCount++;
                    $this->info(sprintf('  fixed: recomputed chain for %s#%d', $type, $id));
                }
            }
        }

        if ($driftCount === 0) {
            $this->info(sprintf('Verified %d account(s); no drift detected.', count($accounts)));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('%d account(s) drifted out of %d checked.', $driftCount, count($accounts)));
        if ($shouldFix) {
            $this->info(sprintf('Repaired %d account(s).', $fixedCount));

            return self::SUCCESS;
        }

        $this->line('Re-run with --fix to repair.');

        return self::FAILURE;
    }

    private function approximatelyEqual(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }
}
