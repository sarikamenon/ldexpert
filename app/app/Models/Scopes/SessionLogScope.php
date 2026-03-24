<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SessionLogScope extends BaseModelScope
{
    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forTherapist(Builder $builder, Model $model, User $therapist): Builder
    {
        return $builder->where(self::qualify($model, 'therapist_id'), $therapist->id);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forTherapistId(Builder $builder, Model $model, int $therapistId): Builder
    {
        return $builder->where(self::qualify($model, 'therapist_id'), $therapistId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @param  array<int, SessionLogStatus|string>  $statuses
     * @return Builder<SessionLog>
     */
    public static function withStatuses(Builder $builder, Model $model, array $statuses): Builder
    {
        return $builder->whereIn(
            self::qualify($model, 'status'),
            array_map(
                static fn (SessionLogStatus|string $status): string => $status instanceof SessionLogStatus
                    ? $status->value
                    : $status,
                $statuses
            )
        );
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function betweenSessionDates(Builder $builder, Model $model, string $startDate, string $endDate): Builder
    {
        return $builder->whereBetween(self::qualify($model, 'session_date'), [$startDate, $endDate]);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function betweenSubmittedDates(Builder $builder, Model $model, string $startDateTime, string $endDateTime): Builder
    {
        return $builder->whereBetween(self::qualify($model, 'submitted_at'), [$startDateTime, $endDateTime]);
    }
}
