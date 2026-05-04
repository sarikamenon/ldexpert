<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceLineType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceLineType $line_type
 * @property Carbon $billing_period_start
 * @property Carbon $billing_period_end
 */
class InvoiceLineItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_type',
        'description',
        'schedule_id',
        'session_log_id',
        'source_invoice_id',
        'billing_period_start',
        'billing_period_end',
        'quantity',
        'unit_price',
        'total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'line_type' => InvoiceLineType::class,
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<SessionLog, $this>
     */
    public function sessionLog(): BelongsTo
    {
        return $this->belongsTo(SessionLog::class, 'session_log_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    /**
     * @param  Builder<InvoiceLineItem>  $query
     * @return Builder<InvoiceLineItem>
     */
    public function scopeAdvanceCharges(Builder $query): Builder
    {
        return $query->where('line_type', InvoiceLineType::ADVANCE_SCHEDULED->value);
    }

    /**
     * @param  Builder<InvoiceLineItem>  $query
     * @return Builder<InvoiceLineItem>
     */
    public function scopeAdjustments(Builder $query): Builder
    {
        return $query->whereIn('line_type', [InvoiceLineType::ADJUST_NO_SHOW->value,
            InvoiceLineType::ADJUST_CANCEL_BILLABLE->value,
            InvoiceLineType::ADJUST_CANCEL_NON_BILLABLE->value,
            InvoiceLineType::ADJUST_EXTRA_SESSION->value,
            InvoiceLineType::ADJUST_RATE_DIFFERENCE->value,
            InvoiceLineType::CARRY_FORWARD_CREDIT->value,
        ]);
    }

    /**
     * @param  Builder<InvoiceLineItem>  $query
     * @return Builder<InvoiceLineItem>
     */
    public function scopeCarryForward(Builder $query): Builder
    {
        return $query->where('line_type', InvoiceLineType::CARRY_FORWARD_CREDIT->value);
    }

    /**
     * @param  Builder<InvoiceLineItem>  $query
     * @return Builder<InvoiceLineItem>
     */
    public function scopeForPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('billing_period_start', $start->toDateString())->where('billing_period_end', $end->toDateString());
    }

    public function isAdvanceCharge(): bool
    {
        return $this->line_type->isAdvanceCharge();
    }

    public function isAdjustment(): bool
    {
        return $this->line_type->isAdjustment();
    }

    public function isCredit(): bool
    {
        return $this->line_type->isCredit();
    }
}
