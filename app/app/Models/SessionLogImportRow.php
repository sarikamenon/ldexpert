<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionLogImportRowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionLogImportRow extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    protected $table = 'session_log_import_rows';

    protected $fillable = [
        'session_log_import_id',
        'row_number',
        'reference_id',
        'status',
        'raw_data',
        'session_log_id',
        'error_message',
        'processed_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => SessionLogImportRowStatus::class,
            'raw_data' => 'array',
            'row_number' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SessionLogImport, $this> */
    public function sessionLogImport(): BelongsTo
    {
        return $this->belongsTo(SessionLogImport::class, 'session_log_import_id');
    }

    /** @return BelongsTo<SessionLog, $this> */
    public function sessionLog(): BelongsTo
    {
        return $this->belongsTo(SessionLog::class, 'session_log_id');
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::PENDING);
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::PROCESSING);
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::DONE);
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopeDuplicate(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::DUPLICATE);
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopeValidationError(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::VALIDATION_ERROR);
    }

    /**
     * @param  Builder<SessionLogImportRow>  $query
     * @return Builder<SessionLogImportRow>
     */
    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', SessionLogImportRowStatus::SKIPPED);
    }
}
