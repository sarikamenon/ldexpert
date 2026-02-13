<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TherapistBillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property TherapistBillStatus $status
 * @property Carbon|null $paid_at
 * @property float $total_due
 * @property float $total_paid
 */
class TherapistBill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'therapist_id',
        'bill_number',
        'billing_period_start',
        'billing_period_end',
        'bill_date',
        'status',
        'subtotal',
        'adjustments_total',
        'total_due',
        'due_date',
        'therapist_name',
        'therapist_email',
        'therapist_phone',
        'therapist_address',
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
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'bill_date' => 'date',
            'due_date' => 'date',
            'status' => TherapistBillStatus::class,
            'subtotal' => 'decimal:2',
            'adjustments_total' => 'decimal:2',
            'total_due' => 'decimal:2',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function sessionLogs(): HasMany
    {
        return $this->hasMany(SessionLog::class, 'therapist_bill_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(TherapistBillPaymentAllocation::class, 'therapist_bill_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function isDraft(): bool
    {
        return $this->status === TherapistBillStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === TherapistBillStatus::SENT;
    }

    public function isPaid(): bool
    {
        return $this->status === TherapistBillStatus::PAID;
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->paymentAllocations()->sum('allocated_amount');
    }

    public function getBalanceRemainingAttribute(): float
    {
        return max(0, (float) $this->total_due - $this->getTotalPaidAttribute());
    }

    public function isFullyPaid(): bool
    {
        return $this->getTotalPaidAttribute() >= (float) $this->total_due;
    }

    public function isPartiallyPaid(): bool
    {
        $totalPaid = $this->getTotalPaidAttribute();

        return $totalPaid > 0 && $totalPaid < (float) $this->total_due;
    }
}
