<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PaymentGateway $gateway
 * @property PaymentGatewayTransactionStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $completed_at
 */
class PaymentGatewayTransaction extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'gateway',
        'gateway_session_id',
        'payment_url',
        'status',
        'amount',
        'currency',
        'error_message',
        'expires_at',
        'completed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentGatewayTransactionStatus::class,
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return HasMany<PaymentGatewayLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class, 'payment_gateway_transaction_id');
    }

    public function isPending(): bool
    {
        return $this->status === PaymentGatewayTransactionStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentGatewayTransactionStatus::COMPLETED;
    }

    public function isExpired(): bool
    {
        if ($this->status === PaymentGatewayTransactionStatus::EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addLog(string $action, string $direction, array $payload, ?string $gatewayEventId = null): PaymentGatewayLog
    {
        return $this->logs()->create([
            'action' => $action,
            'direction' => $direction,
            'payload' => $payload,
            'gateway_event_id' => $gatewayEventId,
        ]);
    }
}
