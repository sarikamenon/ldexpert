<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleSubCoverageStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ScheduleScope extends BaseModelScope
{
    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function scheduled(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::SCHEDULED->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function completed(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::COMPLETED->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function cancelled(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::CANCELLED->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @param  array<int, ScheduleStatus|string>  $statuses
     * @return Builder<Schedule>
     */
    public static function withStatuses(Builder $builder, Model $model, array $statuses): Builder
    {
        return $builder->whereIn(
            self::qualify($model, 'status'),
            array_map(
                static fn (ScheduleStatus|string $status): string => $status instanceof ScheduleStatus
                    ? $status->value
                    : $status,
                $statuses
            )
        );
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function pendingBilling(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::PENDING->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function billed(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::BILLED->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function notBillable(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::NOT_BILLABLE->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function unbilled(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::PENDING->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function scheduleDateFrom(Builder $builder, Model $model, string $fromDate): Builder
    {
        return $builder->where(self::qualify($model, 'schedule_date'), '>=', $fromDate);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function recurring(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'recurrence_type'), '!=', RecurrenceType::NONE->value);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function single(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'recurrence_type'), RecurrenceType::NONE->value)
            ->whereNull(self::qualify($model, 'parent_schedule_id'));
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function group(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'is_group'), true);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function forTherapist(Builder $builder, Model $model, User $therapist): Builder
    {
        return $builder->where(function (Builder $q) use ($model, $therapist): void {
            $q->where(self::qualify($model, 'therapist_id'), $therapist->id)
                ->orWhere(self::qualify($model, 'sub_therapist_id'), $therapist->id);
        });
    }

    /**
     * For pending-queue and pending-count queries: ensures the original therapist's
     * schedule is hidden once a sub has accepted (they shouldn't log it; the sub will).
     * The sub's own covered schedules still appear.
     *
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function hidingAcceptedCoveredForOriginal(Builder $builder, Model $model, User $therapist): Builder
    {
        $subStatusCol = self::qualify($model, 'sub_request_status');
        $subTherapistCol = self::qualify($model, 'sub_therapist_id');

        return $builder->where(function (Builder $q) use ($subStatusCol, $subTherapistCol, $therapist): void {
            $q->where($subStatusCol, '!=', ScheduleSubCoverageStatus::ACCEPTED->value)
                ->orWhereNull($subStatusCol)
                ->orWhere($subTherapistCol, $therapist->id);
        });
    }

    /**
     * Filter to schedules whose combined (schedule_date + start_time) UTC instant
     * is strictly after the given moment. Used by sub-coverage listings to hide
     * sessions that have already started.
     *
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function startingAfter(Builder $builder, Model $model, CarbonInterface $moment): Builder
    {
        return self::compareStartTimestamp($builder, $model, '>', $moment);
    }

    /**
     * Filter to schedules whose combined (schedule_date + start_time) UTC instant
     * is at or before the given moment. Used by the auto-expiry sweep.
     *
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function startingAtOrBefore(Builder $builder, Model $model, CarbonInterface $moment): Builder
    {
        return self::compareStartTimestamp($builder, $model, '<=', $moment);
    }

    /**
     * Shared whereRaw for the combined (schedule_date + start_time) UTC instant.
     * MySQL CONVERT_TZ is not available in every environment, so we keep the
     * comparison in PHP-formatted UTC and let the DB do a TIMESTAMP() compose.
     *
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    private static function compareStartTimestamp(Builder $builder, Model $model, string $operator, CarbonInterface $moment): Builder
    {
        return $builder->whereRaw(
            'TIMESTAMP('.self::qualify($model, 'schedule_date').', '.self::qualify($model, 'start_time').") {$operator} ?",
            [$moment->copy()->setTimezone('UTC')->format('Y-m-d H:i:s')]
        );
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function forStudent(Builder $builder, Model $model, User $student): Builder
    {
        return $builder->where(self::qualify($model, 'student_id'), $student->id);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function forSSA(Builder $builder, Model $model, ServiceSupportAgreement $ssa): Builder
    {
        return $builder->where(self::qualify($model, 'ssa_id'), $ssa->id);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function betweenScheduleDates(Builder $builder, Model $model, string $startDate, string $endDate): Builder
    {
        return $builder->whereBetween(self::qualify($model, 'schedule_date'), [$startDate, $endDate]);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function byRecurringBatch(Builder $builder, Model $model, string $batchNumber): Builder
    {
        return $builder->where(self::qualify($model, 'recurring_batch_number'), $batchNumber);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function byGroupBatch(Builder $builder, Model $model, string $batchNumber): Builder
    {
        return $builder->where(self::qualify($model, 'group_batch_number'), $batchNumber);
    }

    /**
     * @param  Builder<Schedule>  $builder
     * @return Builder<Schedule>
     */
    public static function forPastSessionsQueue(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'is_billable'), true);
    }
}
