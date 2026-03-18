<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionLogImportStatus;
use App\Enums\SessionLogImportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SessionLogImportStatus $status
 * @property SessionLogImportType $type
 */
class SessionLogImport extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    protected $table = 'session_log_imports';

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'file_name',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => SessionLogImportType::class,
            'status' => SessionLogImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SessionLogImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SessionLogImportRow::class, 'session_log_import_id', 'id');
    }

    /**
     * @param  Builder<SessionLogImport>  $query
     * @return Builder<SessionLogImport>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportStatus::PENDING);
    }

    /**
     * @param  Builder<SessionLogImport>  $query
     * @return Builder<SessionLogImport>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportStatus::PROCESSING);
    }

    /**
     * @param  Builder<SessionLogImport>  $query
     * @return Builder<SessionLogImport>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportStatus::COMPLETED);
    }

    /**
     * @param  Builder<SessionLogImport>  $query
     * @return Builder<SessionLogImport>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportStatus::FAILED);
    }
}
