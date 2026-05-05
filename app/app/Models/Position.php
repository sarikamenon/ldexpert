<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PositionStatus;
use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Position extends Model
{
    use HasAudits;

    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => PositionStatus::class,
    ];

    /** @return HasMany<\App\Models\TherapistProfile, $this> */
    public function therapistProfiles(): HasMany
    {
        return $this->hasMany(TherapistProfile::class);
    }

    /** @return BelongsToMany<\App\Models\Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * Stable snapshot of service IDs for audit diffs.
     * Sorted ascending so the diff is insensitive to insertion order.
     *
     * @return array<int, int>
     */
    public function serviceIdsSnapshot(): array
    {
        /** @var array<int, int> $ids */
        $ids = $this->services()->pluck('services.id')->all();
        sort($ids);

        return $ids;
    }

    /**
     * @param  Builder<Position>  $query
     * @return Builder<Position>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PositionStatus::ACTIVE);
    }
}
