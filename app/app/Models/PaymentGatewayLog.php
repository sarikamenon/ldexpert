<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentGatewayLogDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PaymentGatewayLogDirection $direction
 * @property array<string, mixed> $payload
 */
class PaymentGatewayLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_gateway_transaction_id',
        'action',
        'direction',
        'payload',
        'gateway_event_id',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'direction' => PaymentGatewayLogDirection::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentGatewayTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayTransaction::class, 'payment_gateway_transaction_id');
    }
}
