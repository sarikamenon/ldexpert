<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property InvoiceStatus $status
 * @property BillingMode $billing_mode
 * @property Carbon|null $paid_at
 * @property float $total
 * @property float $total_paid
 * @property float $carry_forward_balance
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property Carbon|null $billing_period_start
 * @property Carbon|null $billing_period_end
 * @property Carbon|null $sent_at
 */
class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'invoice_number',
        'invoice_date',
        'billing_period_start',
        'billing_period_end',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'due_date',
        'school_name',
        'school_display_name',
        'school_address',
        'school_state',
        'school_contact_first_name',
        'school_contact_last_name',
        'school_contact_phone',
        'school_contact_email',
        'school_invoice_email',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_tax_id',
        'billing_mode',
        'carry_forward_balance',
        'sent_at',
        'sent_by_id',
        'paid_at',
        'payment_token',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'billing_mode' => BillingMode::class,
            'carry_forward_balance' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * @return HasMany<SessionLog, $this>
     */
    public function sessionLogs(): HasMany
    {
        return $this->hasMany(SessionLog::class, 'invoice_id');
    }

    /**
     * @return HasMany<InvoiceLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id')
            ->orderBy('sort_order');
    }

    /**
     * @return MorphMany<BillingReminder, $this>
     */
    public function billingReminders(): MorphMany
    {
        return $this->morphMany(BillingReminder::class, 'remindable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class, 'invoice_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function isAdvanceMode(): bool
    {
        return $this->billing_mode === BillingMode::ADVANCE;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === InvoiceStatus::SENT;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function isZeroAmount(): bool
    {
        return (float) $this->total <= 0.0;
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->paymentAllocations()->sum('allocated_amount');
    }

    public function getBalanceRemainingAttribute(): float
    {
        return max(0, (float) $this->total - $this->getTotalPaidAttribute());
    }

    public function isFullyPaid(): bool
    {
        return $this->getTotalPaidAttribute() >= (float) $this->total;
    }

    public function isPartiallyPaid(): bool
    {
        $totalPaid = $this->getTotalPaidAttribute();

        return $totalPaid > 0 && $totalPaid < (float) $this->total;
    }

    /**
     * @return HasMany<InvoiceEmailLog, $this>
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(InvoiceEmailLog::class, 'invoice_id');
    }

    /**
     * @return HasMany<PaymentGatewayTransaction, $this>
     */
    public function gatewayTransactions(): HasMany
    {
        return $this->hasMany(PaymentGatewayTransaction::class, 'invoice_id');
    }

    public function ensurePaymentToken(): string
    {
        if (! $this->payment_token) {
            $token = Str::uuid()->toString();
            $this->update(['payment_token' => $token]);

            return $token;
        }

        return $this->payment_token;
    }

    public function getPaymentUrl(): ?string
    {
        if (! $this->payment_token) {
            return null;
        }

        return route('payment.show', $this->payment_token);
    }
}
