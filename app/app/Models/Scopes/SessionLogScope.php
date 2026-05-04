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
     * KNOWN GAP — compares against UTC `session_date` after the UTC migration,
     * not the therapist's local date. A session at 11pm PT April 30 stored as
     * `2026-05-01 UTC` will land in the wrong bucket. Fix later by accepting
     * a TZ and converting bounds via `UserTimezoneService::userDayUtcRange()`.
     * See `_local_docs/session-logs-utc-migration-plan.md` ("Known gaps").
     *
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

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forTherapistBill(Builder $builder, Model $model, int $billId): Builder
    {
        return $builder->where(self::qualify($model, 'therapist_bill_id'), $billId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function withoutTherapistBill(Builder $builder, Model $model): Builder
    {
        return $builder->whereNull(self::qualify($model, 'therapist_bill_id'));
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forStudentId(Builder $builder, Model $model, int $studentId): Builder
    {
        return $builder->where(self::qualify($model, 'student_id'), $studentId);
    }

    /**
     * Logs that have a non-null outcome and contributed positive THO minutes.
     *
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function withTrackedThoMinutes(Builder $builder, Model $model): Builder
    {
        return $builder
            ->whereNotNull(self::qualify($model, 'outcome'))
            ->where(self::qualify($model, 'tho_minutes'), '>', 0);
    }
}
