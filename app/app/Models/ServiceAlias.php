<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ServiceAlias extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @param  Builder<ServiceAlias>  $query
     * @return Builder<ServiceAlias>
     */
    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Search by external_name or linked service name.
     *
     * @param  Builder<ServiceAlias>  $query
     * @return Builder<ServiceAlias>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $escaped = addcslashes($term, '%_');
        $like = "%{$escaped}%";

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('external_name', 'like', $like)
                ->orWhereHas('service', function (Builder $sq) use ($like): void {
                    $sq->where('name', 'like', $like); // @phpstan-ignore argument.type
                });
        });
    }
}
