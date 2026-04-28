<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Carbon $paid_at
 * @property PaymentMethod $method
 */
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
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'reference');
    }

    /**
     * @return HasMany<TherapistBillPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(TherapistBillPaymentAllocation::class, 'therapist_bill_payment_id');
    }

    /**
     * Single therapist bill this payment is for (1:1).
     *
     * @return BelongsTo<TherapistBill, $this>
     */
    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }

}
