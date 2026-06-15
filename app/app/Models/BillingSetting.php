<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\GenerationDayType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property BillingFrequency $default_frequency
 * @property GenerationDayType $default_generation_day_type
 * @property BillingFrequency $advance_default_frequency
 * @property GenerationDayType $advance_default_generation_day_type
 * @property int $advance_default_generation_day_of_week
 * @property int $advance_default_min_grace_days
 * @property int $advance_default_payment_terms_days
 * @property bool $advance_default_auto_generate
 * @property bool $advance_default_auto_send
 */
class BillingSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'default_frequency',
        'default_generation_day_type',
        'default_generation_day_of_week',
        'default_min_grace_days',
        'default_payment_terms_days',
        'default_auto_generate',
        'default_auto_send',
        'advance_default_frequency',
        'advance_default_generation_day_type',
        'advance_default_generation_day_of_week',
        'advance_default_min_grace_days',
        'advance_default_payment_terms_days',
        'advance_default_auto_generate',
        'advance_default_auto_send',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'reminder_overdue_repeat_days',
        'max_overdue_reminders',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'default_frequency' => BillingFrequency::class,
            'default_generation_day_type' => GenerationDayType::class,
            'default_generation_day_of_week' => 'integer',
            'default_min_grace_days' => 'integer',
            'default_payment_terms_days' => 'integer',
            'default_auto_generate' => 'boolean',
            'default_auto_send' => 'boolean',
            'advance_default_frequency' => BillingFrequency::class,
            'advance_default_generation_day_type' => GenerationDayType::class,
            'advance_default_generation_day_of_week' => 'integer',
            'advance_default_min_grace_days' => 'integer',
            'advance_default_payment_terms_days' => 'integer',
            'advance_default_auto_generate' => 'boolean',
            'advance_default_auto_send' => 'boolean',
            'reminder_days_before_due' => 'integer',
            'reminder_days_after_due' => 'integer',
            'reminder_overdue_repeat_days' => 'integer',
            'max_overdue_reminders' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public static function getSettings(): self
    {
        /** @var self $settings */
        $settings = self::query()->firstOrFail();

        return $settings;
    }
}
