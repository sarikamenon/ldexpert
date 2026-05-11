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
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forSsaId(Builder $builder, Model $model, int $ssaId): Builder
    {
        return $builder->where(self::qualify($model, 'ssa_id'), $ssaId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forSchoolId(Builder $builder, Model $model, int $schoolId): Builder
    {
        return $builder->where(self::qualify($model, 'school_id'), $schoolId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forServiceId(Builder $builder, Model $model, int $serviceId): Builder
    {
        return $builder->where(self::qualify($model, 'service_id'), $serviceId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function forScheduleId(Builder $builder, Model $model, int $scheduleId): Builder
    {
        return $builder->where(self::qualify($model, 'schedule_id'), $scheduleId);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @param  array<int, int>  $scheduleIds
     * @return Builder<SessionLog>
     */
    public static function forScheduleIds(Builder $builder, Model $model, array $scheduleIds): Builder
    {
        return $builder->whereIn(self::qualify($model, 'schedule_id'), $scheduleIds);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function withoutSchedule(Builder $builder, Model $model): Builder
    {
        return $builder->whereNull(self::qualify($model, 'schedule_id'));
    }

    /**
     * KNOWN GAP — see `betweenSessionDates`. Compares against the UTC
     * `session_date` column rather than the therapist's local date.
     *
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function sessionDateFrom(Builder $builder, Model $model, string $date): Builder
    {
        return $builder->whereDate(self::qualify($model, 'session_date'), '>=', $date);
    }

    /**
     * KNOWN GAP — see `betweenSessionDates`. Compares against the UTC
     * `session_date` column rather than the therapist's local date.
     *
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function sessionDateTo(Builder $builder, Model $model, string $date): Builder
    {
        return $builder->whereDate(self::qualify($model, 'session_date'), '<=', $date);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @param  array<int, int>  $studentIds
     * @return Builder<SessionLog>
     */
    public static function forStudentIds(Builder $builder, Model $model, array $studentIds): Builder
    {
        return $builder->whereIn(self::qualify($model, 'student_id'), $studentIds);
    }

    /**
     * @param  Builder<SessionLog>  $builder
     * @param  array<int, int>  $therapistIds
     * @return Builder<SessionLog>
     */
    public static function forTherapistIds(Builder $builder, Model $model, array $therapistIds): Builder
    {
        return $builder->whereIn(self::qualify($model, 'therapist_id'), $therapistIds);
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

    /**
     * @param  Builder<SessionLog>  $builder
     * @return Builder<SessionLog>
     */
    public static function excludingId(Builder $builder, Model $model, int $sessionLogId): Builder
    {
        return $builder->where(self::qualify($model, 'id'), '!=', $sessionLogId);
    }
}
