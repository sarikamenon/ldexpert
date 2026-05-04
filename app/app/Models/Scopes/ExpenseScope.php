<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Expense;
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
}
