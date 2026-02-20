<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SSAImportRowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SSAImportRow extends Model
{
    use HasFactory;

    protected $table = 'ssa_import_rows';

    protected $fillable = [
        'ssa_import_id',
        'row_number',
        'status',
        'raw_data',
        'ssa_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SSAImportRowStatus::class,
            'raw_data' => 'array',
            'row_number' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SSAImport, SSAImportRow> */
    public function ssaImport(): BelongsTo
    {
        return $this->belongsTo(SSAImport::class, 'ssa_import_id');
    }

    /** @return BelongsTo<ServiceSupportAgreement, SSAImportRow> */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::PROCESSING);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::DONE);
    }

    public function scopeDuplicate(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::DUPLICATE);
    }

    public function scopeValidationError(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::VALIDATION_ERROR);
    }
}
