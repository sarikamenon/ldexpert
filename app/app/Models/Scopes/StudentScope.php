<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\UserStatus;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class StudentScope extends BaseModelScope
{
    /**
     * @param  Builder<StudentProfile>  $builder
     * @return Builder<StudentProfile>
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
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('id_number', 'like', $like)
                ->orWhereHas('user', function (Builder $q) use ($like) {
                    $q->where('email', 'like', $like); // @phpstan-ignore argument.type
                });
        });
    }

    /**
     * @param  Builder<StudentProfile>  $builder
     * @return Builder<StudentProfile>
     */
    public static function active(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return $builder->whereHas('user', function (Builder $query) {
            $query->where('status', UserStatus::ACTIVE); // @phpstan-ignore argument.type
        });
    }

    /**
     * @param  Builder<StudentProfile>  $builder
     * @return Builder<StudentProfile>
     */
    public static function inactive(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return $builder->whereHas('user', function (Builder $query) {
            $query->where('status', UserStatus::INACTIVE); // @phpstan-ignore argument.type
        });
    }

    /**
     * @param  Builder<StudentProfile>  $builder
     * @param  array<int, int>  $userIds
     * @return Builder<StudentProfile>
     */
    public static function forUserIds(Builder $builder, array $userIds): Builder
    {
        return $builder->whereIn('user_id', $userIds);
    }

    /**
     * @param  Builder<StudentProfile>  $builder
     * @return Builder<StudentProfile>
     */
    public static function withSchool(Builder $builder): Builder
    {
        return $builder->whereNotNull('school_id');
    }
}
