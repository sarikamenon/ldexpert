<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SSAImportStatus;
use App\Enums\SSAImportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SSAImportStatus $status
 * @property SSAImportType $type
 */
class SSAImport extends Model
{
    
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    protected $table = 'ssa_imports';

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
            'type' => SSAImportType::class,
            'status' => SSAImportStatus::class,
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

    /** @return HasMany<SSAImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SSAImportRow::class, 'ssa_import_id', 'id');
    }

    /**
     * @param  Builder<SSAImport>  $query
     * @return Builder<SSAImport>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::PENDING);
    }

    /**
     * @param  Builder<SSAImport>  $query
     * @return Builder<SSAImport>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::PROCESSING);
    }

    /**
     * @param  Builder<SSAImport>  $query
     * @return Builder<SSAImport>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::COMPLETED);
    }

    /**
     * @param  Builder<SSAImport>  $query
     * @return Builder<SSAImport>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::FAILED);
    }
}
