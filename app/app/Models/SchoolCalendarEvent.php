<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SchoolCalendarEventType;
use App\Observers\SchoolCalendarEventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SchoolCalendarEventType $event_type
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property bool $request_makeup
 * @property \Carbon\Carbon|null $reminder_date
 * @property \Carbon\Carbon|null $response_date
 */
#[ObservedBy([SchoolCalendarEventObserver::class])]
class SchoolCalendarEvent extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolCalendarEventFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'title',
        'event_type',
        'start_date',
        'end_date',
        'request_makeup',
        'reminder_date',
        'response_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SchoolCalendarEventType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'request_makeup' => 'boolean',
            'reminder_date' => 'date',
            'response_date' => 'date',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<ScheduleMakeupRequest, $this> */
    public function makeupRequests(): HasMany
    {
        return $this->hasMany(ScheduleMakeupRequest::class, 'school_calendar_event_id');
    }

    /**
     * Events whose [start_date, end_date] overlaps the given inclusive window.
     *
     * @param  Builder<SchoolCalendarEvent>  $query
     * @return Builder<SchoolCalendarEvent>
     */
    public function scopeOverlappingDateRange(Builder $query, string $fromDate, string $toDate): Builder
    {
        return $query
            ->where('start_date', '<=', $toDate)
            ->where('end_date', '>=', $fromDate);
    }
}
