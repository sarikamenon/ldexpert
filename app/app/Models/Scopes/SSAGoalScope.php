<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\SSAGoalStatus;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SSAGoalScope extends BaseModelScope
{
    /**
     * @param  Builder<SSAGoal>  $builder
     * @return Builder<SSAGoal>
     */
    public static function activeStatus(Builder $builder, Model $model): Builder
    {
        return $builder->where(self::qualify($model, 'status'), SSAGoalStatus::ACTIVE->value);
    }

    /**
     * @param  Builder<SSAGoal>  $builder
     * @return Builder<SSAGoal>
     */
    public static function forSsa(Builder $builder, Model $model, int $ssaId): Builder
    {
        return $builder->where(self::qualify($model, 'ssa_id'), $ssaId);
    }

    /**
     * @param  Builder<SSAGoal>  $builder
     * @return Builder<SSAGoal>
     */
    public static function forStudent(Builder $builder, Model $model, int $studentId): Builder
    {
        return $builder->where(self::qualify($model, 'student_id'), $studentId);
    }

    /**
     * Active goals first, then by created_at ASC within each group.
     *
     * @param  Builder<SSAGoal>  $builder
     * @return Builder<SSAGoal>
     */
    public static function orderForList(Builder $builder, Model $model): Builder
    {
        $statusCol = self::qualify($model, 'status');
        $createdCol = self::qualify($model, 'created_at');

        return $builder
            ->orderByRaw("CASE WHEN {$statusCol} = ? THEN 0 ELSE 1 END", [SSAGoalStatus::ACTIVE->value])
            ->orderBy($createdCol, 'asc');
    }
}
