<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Models\Concerns\HasAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Service extends Model
{
    use HasAudits;

    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    use SoftDeletes;

    public const DELIVERY_MODE_OPTIONS = [
        'virtual' => 'Virtual',
        'in_person' => 'In Person',
        'hybrid' => 'Hybrid',
    ];

    public const DEFAULT_DELIVERY_MODE = 'virtual';

    protected $fillable = [
        'name',
        'description',
        'color',
        'send_email',
        'is_direct_service',
        'is_group_service',
        'is_frequency_service',
        'include_in_tho',
        'delivery_mode',
        'is_billable',
        'min_duration_minutes',
        'max_duration_minutes',
        'status',
    ];

    protected $casts = [
        'is_direct_service' => 'boolean',
        'is_group_service' => 'boolean',
        'is_frequency_service' => 'boolean',
        'include_in_tho' => 'boolean',
        'is_billable' => 'boolean',
        'send_email' => 'boolean',
        'delivery_mode' => 'string',
        'color' => 'string',
        'min_duration_minutes' => 'integer',
        'max_duration_minutes' => 'integer',
        'status' => ServiceStatus::class,
    ];

    /** @return HasMany<TherapistContractService, $this> */
    public function therapistContractServices(): HasMany
    {
        return $this->hasMany(TherapistContractService::class);
    }

    /** @return HasMany<SchoolContractService, $this> */
    public function schoolContractServices(): HasMany
    {
        return $this->hasMany(SchoolContractService::class);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
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

    public function allowsScheduleEmail(): bool
    {
        if ($this->is_direct_service) {
            return true;
        }

        return $this->send_email;
    }
}
