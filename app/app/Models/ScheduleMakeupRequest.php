<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ScheduleMakeupRequestStatus $status
 * @property ScheduleMakeupRespondedByType|null $responded_by_type
 * @property ScheduleMakeupResponseSource|null $response_source
 * @property string $batch_number
 * @property string $response_token
 * @property \Carbon\Carbon $event_date
 * @property \Carbon\Carbon $reminder_date
 * @property \Carbon\Carbon $response_date
 * @property \Carbon\Carbon $deadline_date
 * @property \Carbon\Carbon|null $reminder_sent_at
 * @property \Carbon\Carbon|null $responded_at
 */
class ScheduleMakeupRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleMakeupRequestFactory> */
    use HasAudits, HasFactory, SoftDeletes;

    protected $fillable = [
        'school_calendar_event_id',
        'schedule_id',
        'student_id',
        'therapist_id',
        'event_date',
        'reminder_date',
        'response_date',
        'deadline_date',
        'status',
        'batch_number',
        'reminder_sent_at',
        'response_token',
        'responded_at',
        'responded_by_type',
        'responded_by_user_id',
        'response_source',
        'decline_reason',
        'makeup_schedule_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduleMakeupRequestStatus::class,
            'responded_by_type' => ScheduleMakeupRespondedByType::class,
            'response_source' => ScheduleMakeupResponseSource::class,
            'event_date' => 'date',
            'reminder_date' => 'date',
            'response_date' => 'date',
            'deadline_date' => 'date',
            'reminder_sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SchoolCalendarEvent, $this> */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(SchoolCalendarEvent::class, 'school_calendar_event_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function makeupSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'makeup_schedule_id');
    }

    /** @return HasMany<ScheduleMakeupRequestEmailLog, $this> */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(ScheduleMakeupRequestEmailLog::class, 'schedule_makeup_request_id');
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ScheduleMakeupRequestStatus::PENDING->value);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeForEvent(Builder $query, SchoolCalendarEvent $event): Builder
    {
        return $query->where('school_calendar_event_id', $event->id);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeWithStatus(Builder $query, ScheduleMakeupRequestStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', ScheduleMakeupRequestStatus::SENT->value);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeForTherapist(Builder $query, User $therapist): Builder
    {
        return $query->where('therapist_id', $therapist->id);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeForBatch(Builder $query, string $batchNumber): Builder
    {
        return $query->where('batch_number', $batchNumber);
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeDueForReminder(Builder $query, \Carbon\CarbonInterface $on): Builder
    {
        return $query
            ->where('status', ScheduleMakeupRequestStatus::PENDING->value)
            ->whereDate('reminder_date', '<=', $on->toDateString());
    }

    /**
     * @param  Builder<ScheduleMakeupRequest>  $query
     * @return Builder<ScheduleMakeupRequest>
     */
    public function scopeOverdueForResponse(Builder $query, \Carbon\CarbonInterface $on): Builder
    {
        return $query
            ->where('status', ScheduleMakeupRequestStatus::SENT->value)
            ->whereNull('responded_at')
            ->whereDate('deadline_date', '<', $on->toDateString());
    }

    public function isPending(): bool
    {
        return $this->status === ScheduleMakeupRequestStatus::PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === ScheduleMakeupRequestStatus::SENT;
    }

    public function isRequested(): bool
    {
        return $this->status === ScheduleMakeupRequestStatus::REQUESTED;
    }

    public function isResponded(): bool
    {
        return $this->responded_at !== null;
    }
}
