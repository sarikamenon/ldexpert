<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\UserStatus;
use App\Models\TherapistProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TherapistScope extends BaseModelScope
{
    /**
     * @param  Builder<TherapistProfile>  $builder
     * @return Builder<TherapistProfile>
     */
    public static function search(Builder $builder, ?string $term): Builder
    {
        if (! $term) {
            return $builder;
        }

        $escaped = addcslashes($term, '%_');

        return $builder->where(function (Builder $query) use ($escaped) {
            $like = "%{$escaped}%";
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('personal_email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    /**
     * @param  Builder<TherapistProfile>  $builder
     * @return Builder<TherapistProfile>
     */
    public static function active(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return $builder->whereHas('user', function (Builder $query) {
            $query->where('status', UserStatus::ACTIVE); // @phpstan-ignore argument.type
        });
    }

    /**
     * @param  Builder<TherapistProfile>  $builder
     * @return Builder<TherapistProfile>
     */
    public static function inactive(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return $builder->whereHas('user', function (Builder $query) {
            $query->where('status', UserStatus::INACTIVE); // @phpstan-ignore argument.type
        });
    }
}
