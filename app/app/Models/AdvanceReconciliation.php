<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Records that an advance schedule's prior-month period has been reconciled by
 * the 10th-of-month catch-up run. Acts as the idempotency guard so a period is
 * never reconciled (and credited) twice.
 *
 * @property Carbon $reconciled_period_start
 * @property Carbon $reconciled_period_end
 * @property Carbon $reconciled_at
 * @property float $net_amount
 */
class AdvanceReconciliation extends Model
{
    /** @use HasFactory<\Database\Factories\AdvanceReconciliationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'billing_schedule_id',
        'school_id',
        'reconciled_period_start',
        'reconciled_period_end',
        'source_invoice_id',
        'credit_note_ledger_entry_id',
        'settlement_invoice_id',
        'net_amount',
        'reconciled_at',
        'recorded_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reconciled_period_start' => 'date',
            'reconciled_period_end' => 'date',
            'reconciled_at' => 'datetime',
            'net_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function settlementInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'settlement_invoice_id');
    }

    /**
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function creditNoteLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'credit_note_ledger_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
