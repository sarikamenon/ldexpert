<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentImportStatus;
use App\Enums\StudentImportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property StudentImportStatus $status
 * @property StudentImportType $type
 */
class StudentImport extends Model
{
    
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'file_name',
        'total_rows',
        'processed_rows',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StudentImportType::class,
            'status' => StudentImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudentImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(StudentImportRow::class);
    }

    /**
     * @param  Builder<StudentImport>  $query
     * @return Builder<StudentImport>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::PENDING);
    }

    /**
     * @param  Builder<StudentImport>  $query
     * @return Builder<StudentImport>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::PROCESSING);
    }

    /**
     * @param  Builder<StudentImport>  $query
     * @return Builder<StudentImport>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::COMPLETED);
    }

    /**
     * @param  Builder<StudentImport>  $query
     * @return Builder<StudentImport>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::FAILED);
    }
}
