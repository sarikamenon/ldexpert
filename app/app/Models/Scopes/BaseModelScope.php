<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModelScope
{
    protected static function qualify(Model $model, string $column): string
    {
        return $model->getTable().'.'.$column;
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected static function applyStatus(Builder $builder, Model $model, string $column, string $value): Builder
    {
        return $builder->where(self::qualify($model, $column), $value);
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public static function active(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return static::applyStatus($builder, $model, $column, 'active');
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public static function inactive(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return static::applyStatus($builder, $model, $column, 'inactive');
    }
}
