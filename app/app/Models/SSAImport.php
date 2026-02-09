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

class SSAImport extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SSAImportRow::class, 'ssa_import_id', 'id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::PROCESSING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SSAImportStatus::FAILED);
    }
}
