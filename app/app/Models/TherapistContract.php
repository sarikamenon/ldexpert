<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Storage\Services\StorageServiceInterface;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TherapistContract extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => ContractStatus::class,
    ];

    /** @return BelongsTo<TherapistProfile, $this> */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class);
    }

    /** @return HasMany<TherapistContractService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(TherapistContractService::class);
    }

    /**
     * @param  Builder<TherapistContract>  $query
     * @return Builder<TherapistContract>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContractStatus::ACTIVE);
    }

    /**
     * @param  Builder<TherapistContract>  $query
     * @return Builder<TherapistContract>
     */
    public function scopeForTherapist(Builder $query, int $therapistId): Builder
    {
        return $query->where('therapist_id', $therapistId);
    }

    public function getFormattedDocumentSizeAttribute(): string
    {
        $size = $this->document_size;

        if ($size === null) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', round($size, 2), $units[$unit]);
    }

    public function getDocumentUrlAttribute(): ?string
    {
        if (empty($this->document_path)) {
            return null;
        }

        /** @var StorageServiceInterface $storageService */
        $storageService = app(StorageServiceInterface::class);

        return $storageService->url($this->document_path);
    }
}
