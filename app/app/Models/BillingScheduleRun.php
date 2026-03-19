<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingScheduleRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property BillingScheduleRunStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $billing_period_start
 * @property Carbon $billing_period_end
 * @property Carbon $generation_date
 */
class BillingScheduleRun extends Model
{
    /** @use HasFactory<\Database\Factories\BillingScheduleRunFactory> */
    use HasFactory;

    protected $fillable = [
        'billing_schedule_id',
        'billing_period_start',
        'billing_period_end',
        'generation_date',
        'status',
        'sessions_found',
        'sessions_from_prior_periods',
        'adjustments_count',
        'adjustment_total',
        'carry_forward_amount',
        'invoice_id',
        'therapist_bill_id',
        'total_amount',
        'auto_sent',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'generation_date' => 'date',
            'status' => BillingScheduleRunStatus::class,
            'sessions_found' => 'integer',
            'sessions_from_prior_periods' => 'integer',
            'adjustments_count' => 'integer',
            'adjustment_total' => 'decimal:2',
            'carry_forward_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'auto_sent' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BillingSchedule, $this>
     */
    public function billingSchedule(): BelongsTo
    {
        return $this->belongsTo(BillingSchedule::class, 'billing_schedule_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<TherapistBill, $this>
     */
    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === BillingScheduleRunStatus::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === BillingScheduleRunStatus::FAILED;
    }

    public function wasSkipped(): bool
    {
        return $this->status === BillingScheduleRunStatus::SKIPPED_NO_SESSIONS;
    }
}
