<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\HasAudits;
use App\Models\Scopes\LedgerEntryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TransactionType $transaction_type
 * @property string $amount
 * @property string $balance_after
 * @property \Carbon\Carbon $recorded_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class LedgerEntry extends Model
{
    use HasAudits;

    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<LedgerEntry>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ledgerable_type',
        'ledgerable_id',
        'transaction_type',
        'amount',
        'balance_after',
        'recorded_at',
        'reference_type',
        'reference_id',
        'notes',
        'recorded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Signed amount this row contributes to the running balance.
     * Positive entries increase the balance; negative entries decrease it.
     */
    public function signedAmount(): float
    {
        return (float) $this->amount * $this->transaction_type->balanceDelta();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return Builder<LedgerEntry>
     */
    public function scopeForReference(Builder $query, Model $reference): Builder
    {
        return LedgerEntryScope::forReference($query, $reference);
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @param  class-string  $ledgerableType
     * @return Builder<LedgerEntry>
     */
    public function scopeForAccount(Builder $query, string $ledgerableType, int $ledgerableId): Builder
    {
        return LedgerEntryScope::forAccount($query, $ledgerableType, $ledgerableId);
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return Builder<LedgerEntry>
     */
    public function scopeChainOrder(Builder $query, string $direction = 'asc'): Builder
    {
        return LedgerEntryScope::chainOrder($query, $direction);
    }
}
