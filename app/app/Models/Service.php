<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DELIVERY_MODE_OPTIONS = [
        'virtual' => 'Virtual',
        'in_person' => 'In Person',
        'hybrid' => 'Hybrid',
    ];

    public const DEFAULT_DELIVERY_MODE = 'virtual';

    protected $guarded = [];

    protected $casts = [
        'is_direct_service' => 'boolean',
        'is_group_service' => 'boolean',
        'is_frequency_service' => 'boolean',
        'is_billable' => 'boolean',
        'delivery_mode' => 'string',
        'min_duration_minutes' => 'integer',
        'max_duration_minutes' => 'integer',
        'status' => ServiceStatus::class,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ServiceStatus::ACTIVE);
    }

    /**
     * @return array<string, string>
     */
    public static function deliveryModeOptions(): array
    {
        return self::DELIVERY_MODE_OPTIONS;
    }

    public static function defaultDeliveryMode(): string
    {
        return self::DEFAULT_DELIVERY_MODE;
    }
}
