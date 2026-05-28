<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\ScheduleMakeupAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ScheduleMakeupAvailabilityScope extends BaseModelScope
{
    /**
     * @param  Builder<ScheduleMakeupAvailability>  $builder
     * @return Builder<ScheduleMakeupAvailability>
     */
    public static function forTherapist(Builder $builder, Model $model, User $therapist): Builder
    {
        return $builder->where(self::qualify($model, 'therapist_id'), $therapist->id);
    }

    /**
     * @param  Builder<ScheduleMakeupAvailability>  $builder
     * @return Builder<ScheduleMakeupAvailability>
     */
    public static function upcomingFromToday(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'availability_date'), '>=', Carbon::today()->toDateString());
    }
}
