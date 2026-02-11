<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TherapistBillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
