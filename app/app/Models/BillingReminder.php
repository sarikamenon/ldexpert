<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingReminderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property BillingReminderType $reminder_type
 * @property Carbon $sent_at
 */
class BillingReminder extends Model
{
    /** @use HasFactory<\Database\Factories\BillingReminderFactory> */
    use HasFactory;

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'reminder_type',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_type' => BillingReminderType::class,
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
