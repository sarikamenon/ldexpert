<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ScheduleScope extends BaseModelScope
{
    public static function scheduled(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::SCHEDULED->value);
    }

    public static function completed(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::COMPLETED->value);
    }

    public static function cancelled(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), ScheduleStatus::CANCELLED->value);
    }

    public static function pendingBilling(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::PENDING->value);
    }

    public static function billed(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::BILLED->value);
    }

    public static function notBillable(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::NOT_BILLABLE->value);
    }

    public static function waived(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'billing_status'), BillingStatus::WAIVED->value);
    }

    public static function recurring(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'recurrence_type'), '!=', RecurrenceType::NONE->value);
    }

    public static function single(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'recurrence_type'), RecurrenceType::NONE->value)
            ->whereNull(self::qualify($model, 'parent_schedule_id'));
    }

    public static function group(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'is_group'), true);
    }

    public static function forTherapist(Builder $builder, Model $model, User $therapist): Builder
    {
        return $builder->where(self::qualify($model, 'therapist_id'), $therapist->id);
    }

    public static function forStudent(Builder $builder, Model $model, User $student): Builder
    {
        return $builder->where(self::qualify($model, 'student_id'), $student->id);
    }

    public static function forSSA(Builder $builder, Model $model, ServiceSupportAgreement $ssa): Builder
    {
        return $builder->where(self::qualify($model, 'ssa_id'), $ssa->id);
    }

    public static function byRecurringBatch(Builder $builder, Model $model, string $batchNumber): Builder
    {
        return $builder->where(self::qualify($model, 'recurring_batch_number'), $batchNumber);
    }

    public static function byGroupBatch(Builder $builder, Model $model, string $batchNumber): Builder
    {
        return $builder->where(self::qualify($model, 'group_batch_number'), $batchNumber);
    }
}

