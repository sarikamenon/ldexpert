<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Expense;
use App\Models\TherapistBillPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ExpenseScope extends BaseModelScope
{
    /**
     * @param  Builder<Expense>  $builder
     * @return Builder<Expense>
     */
    public static function forSource(Builder $builder, Model $source): Builder
    {
        return $builder
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey());
    }

    /**
     * Exclude expenses that mirror a therapist bill payment. Those rows are the
     * payout side of a therapist bill and are already counted as therapist
     * payments elsewhere; including them here would double-count the same cash.
     *
     * @param  Builder<Expense>  $builder
     * @return Builder<Expense>
     */
    public static function excludingTherapistPayouts(Builder $builder): Builder
    {
        return $builder->where(
            static fn (Builder $query): Builder => $query
                ->whereNull('source_type')
                ->orWhere('source_type', '!=', TherapistBillPayment::class)
        );
    }
}
