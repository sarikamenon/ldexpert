<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSubRequestInvitee extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleSubRequestInviteeFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_sub_request_id',
        'therapist_id',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
        return $query->where('status', 'invited');
    }

    /**
     * @param  Builder<ScheduleSubRequestInvitee>  $query
     * @return Builder<ScheduleSubRequestInvitee>
     */
    public function scopeForTherapist(Builder $query, User $therapist): Builder
    {
        return $query->where('therapist_id', $therapist->id);
    }
}
