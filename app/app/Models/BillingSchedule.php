<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\BillingMode;
use App\Enums\BillingScheduleType;
use App\Enums\GenerationDayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property BillingScheduleType $schedule_type
 * @property BillingMode $billing_mode
 * @property BillingFrequency $frequency
 * @property GenerationDayType $generation_day_type
 * @property Carbon|null $billing_start_date
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_period_end
 */
class BillingSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\BillingScheduleFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'schedule_type',
        'billing_mode',
        'frequency',
        'generation_day_type',
        'generation_day_of_week',
        'generation_delay_days',
        'min_grace_days',
        'payment_terms_days',
        'auto_generate',
        'auto_send',
        'is_active',
        'last_run_at',
        'last_period_end',
        'next_run_at',
        'notes',
        'billing_start_date',
    ];

    protected function casts(): array
    {
        return [
            'schedule_type' => BillingScheduleType::class,
            'billing_mode' => BillingMode::class,
            'frequency' => BillingFrequency::class,
            'generation_day_type' => GenerationDayType::class,
            'generation_day_of_week' => 'integer',
            'generation_delay_days' => 'integer',
            'min_grace_days' => 'integer',
            'payment_terms_days' => 'integer',
            'auto_generate' => 'boolean',
            'auto_send' => 'boolean',
            'is_active' => 'boolean',
            'billing_start_date' => 'date',
            'last_run_at' => 'datetime',
            'last_period_end' => 'date',
            'next_run_at' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<BillingScheduleRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(BillingScheduleRun::class, 'billing_schedule_id');
    }

    /**
     * @return HasOne<BillingScheduleRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(BillingScheduleRun::class, 'billing_schedule_id')
            ->latestOfMany();
    }

    /**
     * @param  Builder<BillingSchedule>  $query
     * @return Builder<BillingSchedule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<BillingSchedule>  $query
     * @return Builder<BillingSchedule>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('auto_generate', true)->where('next_run_at', '<=', now()->toDateString());
    }

    /**
     * @param  Builder<BillingSchedule>  $query
     * @return Builder<BillingSchedule>
     */
    public function scopeForSchools(Builder $query): Builder
    {
        return $query->where('schedule_type', BillingScheduleType::SCHOOL_INVOICE->value);
    }

    /**
     * @param  Builder<BillingSchedule>  $query
     * @return Builder<BillingSchedule>
     */
    public function scopeForTherapists(Builder $query): Builder
    {
        return $query->where('schedule_type', BillingScheduleType::THERAPIST_BILL->value);
    }

    public function isDue(): bool
    {
        return $this->is_active
            && $this->auto_generate
            && $this->next_run_at !== null
            && $this->next_run_at->lte(now());
    }

    public function isForSchool(): bool
    {
        return $this->schedule_type === BillingScheduleType::SCHOOL_INVOICE;
    }

    public function isForTherapist(): bool
    {
        return $this->schedule_type === BillingScheduleType::THERAPIST_BILL;
    }

    public function isAdvanceMode(): bool
    {
        return $this->billing_mode === BillingMode::ADVANCE;
    }
}
