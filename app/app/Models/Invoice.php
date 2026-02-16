<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceStatus $status
 * @property Carbon|null $paid_at
 * @property float $total
 * @property float $total_paid
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
        'sent_at',
        'sent_by_id',
        'paid_at',
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
     * @return BelongsTo<School, Invoice>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * @return HasMany<SessionLog, Invoice>
     */
    public function sessionLogs(): HasMany
    {
        return $this->hasMany(SessionLog::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<User, Invoice>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, Invoice>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class, 'invoice_id');
    }

    /**
     * @return HasMany<LedgerEntry, Invoice>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'reference_id')
            ->where('reference_type', self::class);
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
}
