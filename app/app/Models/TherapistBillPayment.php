<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TherapistBillPayment extends Model
{
    /** @use HasFactory<\Database\Factories\TherapistBillPaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'therapist_id',
        'therapist_bill_id',
        'paid_at',
        'amount',
        'method',
        'reference',
        'notes',
        'recorded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, TherapistBillPayment>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return BelongsTo<User, TherapistBillPayment>
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * @return MorphMany<LedgerEntry, TherapistBillPayment>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'reference');
    }

    /**
     * @return HasMany<TherapistBillPaymentAllocation, TherapistBillPayment>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(TherapistBillPaymentAllocation::class, 'therapist_bill_payment_id');
    }

    /**
     * Single therapist bill this payment is for (1:1).
     *
     * @return BelongsTo<TherapistBill, TherapistBillPayment>
     */
    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }
}
