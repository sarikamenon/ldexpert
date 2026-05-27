<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Carbon\Carbon $availability_date
 * @property \Carbon\Carbon $start_time
 * @property \Carbon\Carbon $end_time
 */
class ScheduleMakeupAvailability extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleMakeupAvailabilityFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'schedule_makeup_availabilities';

    protected $fillable = [
        'therapist_id',
        'availability_date',
        'start_time',
        'end_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'availability_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
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
}
