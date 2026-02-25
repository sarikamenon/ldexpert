<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property TransactionType $transaction_type
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<LedgerEntry>> */
    use HasFactory;

    protected $fillable = [
        'ledgerable_type',
        'ledgerable_id',
        'transaction_type',
        'amount',
        'balance_after',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, LedgerEntry>
     */
    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, LedgerEntry>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, LedgerEntry>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
