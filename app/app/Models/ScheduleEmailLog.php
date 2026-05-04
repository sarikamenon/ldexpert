<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleEmailType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ScheduleEmailType $type
 * @property Carbon $sent_at
 * @property string|null $sent_at_formatted Set transiently by controllers — pre-formatted in viewer/owner timezone for display.
 * @property string|null $schedule_local_date Set transiently by controllers — schedule date pre-formatted in viewer/owner timezone for display.
 */
class ScheduleEmailLog extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_id',
        'type',
        'recipient_email',
        'custom_message',
        'sent_by_id',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScheduleEmailType::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }
}
