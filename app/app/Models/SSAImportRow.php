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
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
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

    /** @return BelongsTo<SSAImport, $this> */
    public function ssaImport(): BelongsTo
    {
        return $this->belongsTo(SSAImport::class, 'ssa_import_id');
    }

    /** @return BelongsTo<ServiceSupportAgreement, $this> */
    public function ssa(): BelongsTo
    {
        return $this->belongsTo(ServiceSupportAgreement::class, 'ssa_id');
    }

    /**
     * @param  Builder<SSAImportRow>  $query
     * @return Builder<SSAImportRow>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::PENDING);
    }

    /**
     * @param  Builder<SSAImportRow>  $query
     * @return Builder<SSAImportRow>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::PROCESSING);
    }

    /**
     * @param  Builder<SSAImportRow>  $query
     * @return Builder<SSAImportRow>
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::DONE);
    }

    /**
     * @param  Builder<SSAImportRow>  $query
     * @return Builder<SSAImportRow>
     */
    public function scopeDuplicate(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::DUPLICATE);
    }

    /**
     * @param  Builder<SSAImportRow>  $query
     * @return Builder<SSAImportRow>
     */
    public function scopeValidationError(Builder $query): Builder
    {
        return $query->where('status', SSAImportRowStatus::VALIDATION_ERROR);
    }
}
