<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

final class SchoolScope extends BaseModelScope
{
    /**
     * @param  Builder<School>  $builder
     * @return Builder<School>
     */
    public static function search(Builder $builder, ?string $term): Builder
    {
        if (! $term) {
            return $builder;
        }

        $escaped = addcslashes($term, '%_');

        return $builder->where(function (Builder $query) use ($escaped) {
            $like = "%{$escaped}%";
            $query->where('full_name', 'like', $like)
                ->orWhere('display_name', 'like', $like)
                ->orWhere('contact_email', 'like', $like);
        });
    }

    /**
     * @param  Builder<School>  $builder
     * @return Builder<School>
     */
    public static function status(Builder $builder, ?SchoolStatus $status): Builder
    {
        if (! $status) {
            return $builder;
        }

        return self::applyStatus($builder, $builder->getModel(), 'status', $status->value);
    }

    /**
     * @param  Builder<School>  $builder
     * @return Builder<School>
     */
    public static function orderedByDisplayName(Builder $builder): Builder
    {
        return $builder->orderByRaw('COALESCE(display_name, full_name) asc');
    }
}
