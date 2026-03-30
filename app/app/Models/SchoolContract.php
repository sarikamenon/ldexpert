<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\HasContractDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolContract extends Model
{
    use HasContractDocument;

    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => ContractStatus::class,
    ];

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<SchoolContractService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(SchoolContractService::class);
    }

    /**
     * @param  Builder<SchoolContract>  $query
     * @return Builder<SchoolContract>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContractStatus::ACTIVE);
    }

    /**
     * @param  Builder<SchoolContract>  $query
     * @return Builder<SchoolContract>
     */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}
