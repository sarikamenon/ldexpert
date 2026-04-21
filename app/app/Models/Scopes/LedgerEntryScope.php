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
}
