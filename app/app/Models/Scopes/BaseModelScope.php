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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    protected static function applyStatus(Builder $builder, Model $model, string $column, string $value): Builder
    {
        return $builder->where(self::qualify($model, $column), $value);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public static function active(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return static::applyStatus($builder, $model, $column, 'active');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public static function inactive(Builder $builder, Model $model, string $column = 'status'): Builder
    {
        return static::applyStatus($builder, $model, $column, 'inactive');
    }
}
