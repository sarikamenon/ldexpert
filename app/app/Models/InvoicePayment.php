<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    /** @use HasFactory<\Database\Factories\InvoicePaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
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
     * @return BelongsTo<User, InvoicePayment>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return MorphMany<LedgerEntry, InvoicePayment>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'reference');
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, InvoicePayment>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class, 'invoice_payment_id');
    }

    /**
     * A payment may be allocated across multiple invoices.
     *
     * @return BelongsToMany<Invoice, InvoicePayment>
     */
    public function invoice(): BelongsToMany
    {
        return $this->belongsToMany(
            Invoice::class,
            'invoice_payment_allocations',
            'invoice_payment_id',
            'invoice_id',
        );
    }
}
