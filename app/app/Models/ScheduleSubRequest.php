<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleSubRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleSubRequestFactory> */
    use HasAudits, HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_id',
        'requested_by_id',
        'reason',
        'status',
        'accepted_by_id',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    /** @return HasOne<ScheduleSubSsa, $this> */
    public function subSsa(): HasOne
    {
        return $this->hasOne(ScheduleSubSsa::class, 'schedule_sub_request_id');
    }

    /** @return HasMany<ScheduleSubRequestInvitee, $this> */
    public function invitees(): HasMany
    {
        return $this->hasMany(ScheduleSubRequestInvitee::class, 'schedule_sub_request_id');
    }

    /**
     * @param  Builder<ScheduleSubRequest>  $query
     * @return Builder<ScheduleSubRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Filters to requests where the requester shares the same position as $sub.
     *
     * @param  Builder<ScheduleSubRequest>  $query
     * @return Builder<ScheduleSubRequest>
     */
    public function scopeForSubPosition(Builder $query, User $sub): Builder
    {
        $subPositionId = $sub->therapistProfile?->position_id;

        if ($subPositionId === null) {
            return $query->whereRaw('0=1');
        }

        return $query->whereHas('requestedBy.therapistProfile', function (Builder $q) use ($subPositionId): void {
            $q->where('position_id', $subPositionId); // @phpstan-ignore argument.type
        });
    }

    /**
     * @param  Builder<ScheduleSubRequest>  $query
     * @return Builder<ScheduleSubRequest>
     */
    public function scopeNotRequestedBy(Builder $query, User $user): Builder
    {
        return $query->where('requested_by_id', '!=', $user->id);
    }

    /**
     * @param  Builder<ScheduleSubRequest>  $query
     * @return Builder<ScheduleSubRequest>
     */
    public function scopeForSchedule(Builder $query, int $scheduleId): Builder
    {
        return $query->where('schedule_id', $scheduleId);
    }

    /**
     * Filters to requests where the given therapist has an `invited` invitee row.
     *
     * @param  Builder<ScheduleSubRequest>  $query
     * @return Builder<ScheduleSubRequest>
     */
    public function scopeInvitedTo(Builder $query, User $sub): Builder
    {
        return $query->whereHas('invitees', function (Builder $q) use ($sub): void {
            $q->where('therapist_id', $sub->id) // @phpstan-ignore argument.type
                ->where('status', 'invited'); // @phpstan-ignore argument.type
        });
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
