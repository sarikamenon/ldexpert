<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ScheduleMakeupEmailLogStatus $status
 * @property ScheduleMakeupEmailLogType $type
 */
class ScheduleMakeupRequestEmailLog extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleMakeupRequestEmailLogFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_makeup_request_id',
        'type',
        'recipient_email',
        'recipient_name',
        'from_email',
        'from_name',
        'subject',
        'status',
        'sent_at',
        'failed_at',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScheduleMakeupEmailLogType::class,
            'status' => ScheduleMakeupEmailLogStatus::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ScheduleMakeupRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ScheduleMakeupRequest::class, 'schedule_makeup_request_id');
    }
}
