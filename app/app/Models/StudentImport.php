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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(StudentImportRow::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::PROCESSING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', StudentImportStatus::FAILED);
    }
}
