<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Models\Scopes\ScheduleScope;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property Carbon $schedule_date
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property Carbon|null $recurrence_end_date
 * @property ScheduleStatus $status
 * @property BillingStatus $billing_status
 * @property RecurrenceType|null $recurrence_type
 * @property bool $is_billable
 */
class Schedule extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'therapist_id',
        'student_id',
        'ssa_id',
        'service_id',
        'school_id',
        'parent_schedule_id',
        'schedule_date',
        'start_time',
        'end_time',
        'recurrence_type',
        'recurrence_end_date',
        'is_group',
        'recurring_batch_number',
        'group_batch_number',
        'status',
        'billing_status',
        'is_billable',
        'notes',
        'location_details',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'recurrence_type' => RecurrenceType::class,
            'recurrence_end_date' => 'date',
            'is_group' => 'boolean',
            'status' => ScheduleStatus::class,
            'billing_status' => BillingStatus::class,
            'is_billable' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<ServiceSupportAgreement, $this>
     */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function parentSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'parent_schedule_id');
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Schedule::class, 'parent_schedule_id');
    }

    /**
     * @return HasMany<ScheduleEmailLog, $this>
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(ScheduleEmailLog::class, 'schedule_id');
    }

    /**
     * @return HasOne<SessionLog, $this>
     */
    public function sessionLog(): HasOne
    {
        return $this->hasOne(SessionLog::class, 'schedule_id');
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return ScheduleScope::scheduled($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return ScheduleScope::completed($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return ScheduleScope::cancelled($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @param  array<int, ScheduleStatus|string>  $statuses
     * @return Builder<Schedule>
     */
    public function scopeWithStatuses(Builder $query, array $statuses): Builder
    {
        return ScheduleScope::withStatuses($query, $this, $statuses);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopePendingBilling(Builder $query): Builder
    {
        return ScheduleScope::pendingBilling($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeBilled(Builder $query): Builder
    {
        return ScheduleScope::billed($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return ScheduleScope::unbilled($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeNotBillable(Builder $query): Builder
    {
        return ScheduleScope::notBillable($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeScheduleDateFrom(Builder $query, string $fromDate): Builder
    {
        return ScheduleScope::scheduleDateFrom($query, $this, $fromDate);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return ScheduleScope::recurring($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeSingle(Builder $query): Builder
    {
        return ScheduleScope::single($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeGroup(Builder $query): Builder
    {
        return ScheduleScope::group($query, $this);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeForTherapist(Builder $query, User $therapist): Builder
    {
        return ScheduleScope::forTherapist($query, $this, $therapist);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeForStudent(Builder $query, User $student): Builder
    {
        return ScheduleScope::forStudent($query, $this, $student);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeForSSA(Builder $query, ServiceSupportAgreement $ssa): Builder
    {
        return ScheduleScope::forSSA($query, $this, $ssa);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeBetweenScheduleDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return ScheduleScope::betweenScheduleDates($query, $this, $startDate, $endDate);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeByRecurringBatch(Builder $query, string $batchNumber): Builder
    {
        return ScheduleScope::byRecurringBatch($query, $this, $batchNumber);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeByGroupBatch(Builder $query, string $batchNumber): Builder
    {
        return ScheduleScope::byGroupBatch($query, $this, $batchNumber);
    }

    /**
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    public function scopeForPastSessionsQueue(Builder $query): Builder
    {
        return ScheduleScope::forPastSessionsQueue($query, $this);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_type !== RecurrenceType::NONE;
    }

    public function isOccurrence(): bool
    {
        return $this->parent_schedule_id !== null;
    }

    public function isGroup(): bool
    {
        return $this->is_group === true;
    }

    public function durationMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return (int) $start->diffInMinutes($end);
    }

    public function startUtc(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->schedule_date->format('Y-m-d').' '.$this->start_time->format('H:i:s'),
            'UTC',
        );
    }

    public function endUtc(): CarbonImmutable
    {
        // end_time may be numerically less than start_time when the session
        // crosses midnight in UTC. Roll the end date forward only when end is
        // strictly before start; equal times mean a zero-duration row (legacy
        // or otherwise) and should not silently become 24 hours.
        $startUtc = $this->startUtc();
        $endSameDay = CarbonImmutable::parse(
            $this->schedule_date->format('Y-m-d').' '.$this->end_time->format('H:i:s'),
            'UTC',
        );

        return $endSameDay->lessThan($startUtc)
            ? $endSameDay->addDay()
            : $endSameDay;
    }

    public function localStart(string $timezone): CarbonImmutable
    {
        return $this->startUtc()->setTimezone($timezone);
    }

    public function localEnd(string $timezone): CarbonImmutable
    {
        return $this->endUtc()->setTimezone($timezone);
    }

    /**
     * Resolve the meeting link for this schedule. Prefers a URL embedded in
     * the schedule's own location_details; falls back to the therapist's
     * default_meeting_location so therapists with a profile-level default
     * don't need to retype it on every schedule.
     */
    public function meetingLink(): ?string
    {
        $fromSchedule = $this->extractUrl($this->location_details);
        if ($fromSchedule !== null) {
            return $fromSchedule;
        }

        return $this->extractUrl($this->therapist?->therapistProfile?->default_meeting_location);
    }

    private function extractUrl(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        if (preg_match('/https?:\/\/[^\s<>"\']+/i', $text, $matches) !== 1) {
            return null;
        }

        $url = rtrim($matches[0], '.,;:)');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * Identify the meeting provider for the meeting link, or null when there
     * is no link. Currently distinguishes Zoom from "other" (Meet, Teams, etc.)
     * so the UI can label the join button accurately.
     */
    public function meetingProvider(): ?string
    {
        $link = $this->meetingLink();
        if ($link === null) {
            return null;
        }

        $host = strtolower((string) (parse_url($link, PHP_URL_HOST) ?? ''));

        return str_contains($host, 'zoom.') ? 'zoom' : 'other';
    }

    /**
     * Resolve the display timezone for this schedule. Per CLAUDE.md, the
     * schedule's owner is its therapist — admin viewing another therapist's
     * schedule still sees the therapist's local time. Falls back through
     * therapist profile → users.timezone → UTC.
     */
    public function displayTimezone(): string
    {
        $therapist = $this->therapist;

        if ($therapist === null) {
            return 'UTC';
        }

        $profileTz = $therapist->therapistProfile?->timezone;
        if ($profileTz !== null && $profileTz !== '') {
            return $profileTz;
        }

        $userTz = (string) ($therapist->timezone ?? '');

        return $userTz !== '' ? $userTz : 'UTC';
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getRecurringOccurrences(): Collection
    {
        if (! $this->recurring_batch_number) {
            return collect([]);
        }

        return self::query()
            ->where('recurring_batch_number', $this->recurring_batch_number)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getGroupSchedules(): Collection
    {
        if (! $this->group_batch_number) {
            return collect([]);
        }

        return self::query()
            ->where('group_batch_number', $this->group_batch_number)
            ->where('id', '!=', $this->id)
            ->get();
    }
}
