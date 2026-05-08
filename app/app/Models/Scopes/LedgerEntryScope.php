<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class LedgerEntryScope extends BaseModelScope
{
    /**
     * @param  Builder<LedgerEntry>  $builder
     * @return Builder<LedgerEntry>
     */
    public static function forReference(Builder $builder, Model $reference): Builder
    {
        return $builder
            ->where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey());
    }

    /**
     * Filter to a single ledger account (morph pair).
     *
     * @param  Builder<LedgerEntry>  $builder
     * @param  class-string  $ledgerableType
     * @return Builder<LedgerEntry>
     */
    public static function forAccount(Builder $builder, string $ledgerableType, int $ledgerableId): Builder
    {
        return $builder
            ->where('ledgerable_type', $ledgerableType)
            ->where('ledgerable_id', $ledgerableId);
    }

    /**
     * Order rows in canonical chain order (oldest → newest).
     * recorded_at, then id as tiebreak — same key the chain walker uses.
     *
     * @param  Builder<LedgerEntry>  $builder
     * @return Builder<LedgerEntry>
     */
    public static function chainOrder(Builder $builder, string $direction = 'asc'): Builder
    {
        $dir = $direction === 'desc' ? 'desc' : 'asc';

        return $builder->orderBy('recorded_at', $dir)->orderBy('id', $dir);
    }

    /**
     * Filter to specific transaction types.
     *
     * @param  Builder<LedgerEntry>  $builder
     * @param  list<string>  $types
     * @return Builder<LedgerEntry>
     */
    public static function ofTypes(Builder $builder, array $types): Builder
    {
        return $builder->whereIn('transaction_type', $types);
    }

    /**
     * Filter entries within an inclusive date range on recorded_at.
     *
     * @param  Builder<LedgerEntry>  $builder
     * @return Builder<LedgerEntry>
     */
    public static function inDateRange(Builder $builder, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $builder->whereDate('recorded_at', '>=', $from);
        }

        if ($to !== null) {
            $builder->whereDate('recorded_at', '<=', $to);
        }

        return $builder;
    }

    /**
     * Filter to a single ledgerable entity (morph pair). No-ops when id is null.
     *
     * @param  Builder<LedgerEntry>  $builder
     * @param  class-string  $ledgerableType
     * @return Builder<LedgerEntry>
     */
    public static function forLedgerable(Builder $builder, string $ledgerableType, ?int $ledgerableId): Builder
    {
        if ($ledgerableId === null) {
            return $builder;
        }

        return $builder
            ->where('ledgerable_type', $ledgerableType)
            ->where('ledgerable_id', $ledgerableId);
    }

    /**
     * Search on the notes column.
     *
     * @param  Builder<LedgerEntry>  $builder
     * @return Builder<LedgerEntry>
     */
    public static function searchNotes(Builder $builder, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $builder;
        }

        return $builder->where('notes', 'like', "%{$term}%");
    }
}
