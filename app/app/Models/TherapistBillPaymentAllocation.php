<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistBillPaymentAllocation extends Model
{
    /** @use HasFactory<\Database\Factories\TherapistBillPaymentAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'therapist_bill_id',
        'therapist_bill_payment_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TherapistBill, $this>
     */
    public function therapistBill(): BelongsTo
    {
        return $this->belongsTo(TherapistBill::class, 'therapist_bill_id');
    }

    /**
     * @return BelongsTo<TherapistBillPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(TherapistBillPayment::class, 'therapist_bill_payment_id');
    }
}
