<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubRequestInviteeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SubRequestInviteeStatus $status
 */
class ScheduleSubRequestInvitee extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleSubRequestInviteeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_sub_request_id',
        'therapist_id',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubRequestInviteeStatus::class,
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ScheduleSubRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubRequest::class, 'schedule_sub_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SubRequestInviteeStatus::INVITED->value);
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopeWithStatus(Builder $query, SubRequestInviteeStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopeForRequest(Builder $query, int $requestId): Builder
    {
        return $query->where('schedule_sub_request_id', $requestId);
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopeForTherapist(Builder $query, User $therapist): Builder
    {
        return $query->where('therapist_id', $therapist->id);
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopeExceptInvitee(Builder $query, int $inviteeId): Builder
    {
        return $query->where('id', '!=', $inviteeId);
    }
}
