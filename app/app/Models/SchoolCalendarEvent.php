<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SchoolCalendarEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SchoolCalendarEventType $event_type
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 */
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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SchoolCalendarEventType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
