<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PositionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Position extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => PositionStatus::class,
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PositionStatus::ACTIVE);
    }
}
