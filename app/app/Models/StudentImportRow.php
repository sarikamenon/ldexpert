<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentImportRowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentImportRow extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'student_import_id',
        'row_number',
        'status',
        'raw_data',
        'student_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentImportRowStatus::class,
            'raw_data' => 'array',
            'row_number' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StudentImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(StudentImport::class, 'student_import_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @param  Builder<StudentImportRow>  $query
     * @return Builder<StudentImportRow>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StudentImportRowStatus::PENDING);
    }

    /**
     * @param  Builder<StudentImportRow>  $query
     * @return Builder<StudentImportRow>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', StudentImportRowStatus::PROCESSING);
    }

    /**
     * @param  Builder<StudentImportRow>  $query
     * @return Builder<StudentImportRow>
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', StudentImportRowStatus::DONE);
    }

    /**
     * @param  Builder<StudentImportRow>  $query
     * @return Builder<StudentImportRow>
     */
    public function scopeDuplicate(Builder $query): Builder
    {
        return $query->where('status', StudentImportRowStatus::DUPLICATE);
    }

    /**
     * @param  Builder<StudentImportRow>  $query
     * @return Builder<StudentImportRow>
     */
    public function scopeValidationError(Builder $query): Builder
    {
        return $query->where('status', StudentImportRowStatus::VALIDATION_ERROR);
    }
}
