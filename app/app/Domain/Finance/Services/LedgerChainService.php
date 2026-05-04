<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Models\LedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Walks and locks the per-account ledger chain. LedgerService owns business
 * rules (which transaction types exist, which can be edited); this class owns
 * the chain mechanics (locking, ordering, balance_after recompute).
 */
class LedgerChainService
{
    /**
     * Tolerance for balance_after equality checks. Storage is decimal(*,2),
     * so anything closer than half a cent is the same number.
     */
    private const BALANCE_EPSILON = 0.005;

    /**
     * Walk the entire chain for an account (in (recorded_at, id) order) and
     * rewrite balance_after on every row whose stored value disagrees with the
     * running signed-amount total.
     *
     * Locks the account chain via SELECT ... FOR UPDATE so concurrent writers
     * serialize per-account. Caller is responsible for opening the surrounding
     * DB transaction (createEntry/editAdjustment/deleteAdjustment all do so);
     * called outside one, the lock is released immediately and the recompute
     * is not race-safe.
     *
     * Soft-deleted rows are excluded by Eloquent's SoftDeletes scope and
     * therefore stop contributing to the running balance.
     *
     * @param  class-string  $ledgerableType
     */
    public function recomputeChain(string $ledgerableType, int $ledgerableId): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $rows */
        $rows = LedgerEntry::query()
            ->forAccount($ledgerableType, $ledgerableId)
            ->chainOrder('asc')
            ->lockForUpdate()
            ->get();

        $running = 0.0;
        foreach ($rows as $row) {
            $running += $row->signedAmount();

            if (abs((float) $row->balance_after - $running) >= self::BALANCE_EPSILON) {
                $row->balance_after = (string) $running;
                $row->save();
            }
        }
    }

    /**
     * Backwards-compatible wrapper kept so external callers (the
     * `ledger:verify --fix` command and a couple of payment services) continue
     * to work after we removed the obsolete `$from` cursor argument from the
     * recompute API. The cursor was never load-bearing — drift older than the
     * cursor used to be silently skipped.
     *
     * @param  class-string  $ledgerableType
     */
    public function recomputeChainFrom(string $ledgerableType, int $ledgerableId, ?CarbonInterface $from = null): void
    {
        unset($from);

        DB::transaction(function () use ($ledgerableType, $ledgerableId): void {
            $this->recomputeChain($ledgerableType, $ledgerableId);
        });
    }

    /**
     * @param  class-string  $ledgerableType
     */
    public function lockedLatestEntry(string $ledgerableType, int $ledgerableId): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->forAccount($ledgerableType, $ledgerableId)
            ->chainOrder('desc')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  class-string  $ledgerableType
     */
    public function getCurrentBalance(string $ledgerableType, int $ledgerableId): float
    {
        $lastEntry = LedgerEntry::query()
            ->forAccount($ledgerableType, $ledgerableId)
            ->chainOrder('desc')
            ->first();

        return $lastEntry !== null ? (float) $lastEntry->balance_after : 0.0;
    }
}
