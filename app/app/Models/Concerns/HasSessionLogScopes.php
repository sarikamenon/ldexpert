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

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForStudentId(Builder $query, int $studentId): Builder
    {
        return SessionLogScope::forStudentId($query, $this, $studentId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeWithTrackedThoMinutes(Builder $query): Builder
    {
        return SessionLogScope::withTrackedThoMinutes($query, $this);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForSsaId(Builder $query, int $ssaId): Builder
    {
        return SessionLogScope::forSsaId($query, $this, $ssaId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForSchoolId(Builder $query, int $schoolId): Builder
    {
        return SessionLogScope::forSchoolId($query, $this, $schoolId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForServiceId(Builder $query, int $serviceId): Builder
    {
        return SessionLogScope::forServiceId($query, $this, $serviceId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForScheduleId(Builder $query, int $scheduleId): Builder
    {
        return SessionLogScope::forScheduleId($query, $this, $scheduleId);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @param  array<int, int>  $scheduleIds
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForScheduleIds(Builder $query, array $scheduleIds): Builder
    {
        return SessionLogScope::forScheduleIds($query, $this, $scheduleIds);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeWithoutSchedule(Builder $query): Builder
    {
        return SessionLogScope::withoutSchedule($query, $this);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeSessionDateFrom(Builder $query, string $date): Builder
    {
        return SessionLogScope::sessionDateFrom($query, $this, $date);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeSessionDateTo(Builder $query, string $date): Builder
    {
        return SessionLogScope::sessionDateTo($query, $this, $date);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @param  array<int, int>  $studentIds
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForStudentIds(Builder $query, array $studentIds): Builder
    {
        return SessionLogScope::forStudentIds($query, $this, $studentIds);
    }

    /**
     * @param  Builder<\App\Models\SessionLog>  $query
     * @param  array<int, int>  $therapistIds
     * @return Builder<\App\Models\SessionLog>
     */
    public function scopeForTherapistIds(Builder $query, array $therapistIds): Builder
    {
        return SessionLogScope::forTherapistIds($query, $this, $therapistIds);
    }
}
