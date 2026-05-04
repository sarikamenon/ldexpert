<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\SessionLogStatus;
use App\Models\Scopes\SessionLogScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasSessionLogScopes
{
    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForTherapist(Builder $query, User $therapist): Builder
    {
        return SessionLogScope::forTherapist($query, $this, $therapist);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForTherapistId(Builder $query, int $therapistId): Builder
    {
        return SessionLogScope::forTherapistId($query, $this, $therapistId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @param  array<int, SessionLogStatus|string>  $statuses
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeWithStatuses(Builder $query, array $statuses): Builder
    {
        return SessionLogScope::withStatuses($query, $this, $statuses);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeBetweenSessionDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return SessionLogScope::betweenSessionDates($query, $this, $startDate, $endDate);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeBetweenSubmittedDates(Builder $query, string $startDateTime, string $endDateTime): Builder
    {
        return SessionLogScope::betweenSubmittedDates($query, $this, $startDateTime, $endDateTime);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForTherapistBill(Builder $query, int $billId): Builder
    {
        return SessionLogScope::forTherapistBill($query, $this, $billId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeWithoutTherapistBill(Builder $query): Builder
    {
        return SessionLogScope::withoutTherapistBill($query, $this);
    }
}
