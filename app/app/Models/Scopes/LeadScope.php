<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class LeadScope extends BaseModelScope
{
    /**
     * @param  Builder<Lead>  $builder
     * @return Builder<Lead>
     */
    public static function search(Builder $builder, ?string $term): Builder
    {
        if (! $term) {
            return $builder;
        }

        $escaped = addcslashes($term, '%_');
        $like = "%{$escaped}%";

        return $builder->where(function (Builder $query) use ($like) {
            $query->where('leads.first_name', 'like', $like)
                ->orWhere('leads.middle_name', 'like', $like)
                ->orWhere('leads.last_name', 'like', $like)
                ->orWhere('leads.email', 'like', $like)
                ->orWhere('leads.parent_guardian_name', 'like', $like)
                ->orWhere('leads.parent_guardian_email', 'like', $like);
        });
    }

    /**
     * @param  Builder<Lead>  $builder
     * @return Builder<Lead>
     */
    public static function byStatus(Builder $builder, ?string $status): Builder
    {
        if (! $status) {
            return $builder;
        }

        return $builder->where('leads.status', $status);
    }

    /**
     * @param  Builder<Lead>  $builder
     * @return Builder<Lead>
     */
    public static function overdueFollowUp(Builder $builder): Builder
    {
        $terminalValues = array_map(
            static fn (LeadStatus $s): string => $s->value,
            LeadStatus::terminalStatuses()
        );

        return $builder->whereNotNull('leads.follow_up_date')
            ->where('leads.follow_up_date', '<', Carbon::today()->toDateString())
            ->whereNotIn('leads.status', $terminalValues);
    }

    /**
     * @param  Builder<Lead>  $builder
     * @return Builder<Lead>
     */
    public static function followUpDueOn(Builder $builder, string $date): Builder
    {
        $terminalValues = array_map(
            static fn (LeadStatus $s): string => $s->value,
            LeadStatus::terminalStatuses()
        );

        return $builder->where('leads.follow_up_date', $date)
            ->whereNotIn('leads.status', $terminalValues);
    }
}
