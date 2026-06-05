<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\GenerationDayType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property BillingFrequency $default_frequency
 * @property GenerationDayType $default_generation_day_type
 * @property int $default_generation_day_of_week
 * @property int $default_delay_days
 * @property int $default_payment_terms_days
 * @property bool $default_auto_generate
 * @property bool $default_auto_send
 * @property BillingFrequency $advance_default_frequency
 * @property GenerationDayType $advance_default_generation_day_type
 * @property int $advance_default_generation_day_of_week
 * @property int $advance_default_delay_days
 * @property int $advance_default_payment_terms_days
 * @property bool $advance_default_auto_generate
 * @property bool $advance_default_auto_send
 * @property BillingFrequency $standard_default_frequency
 * @property GenerationDayType $standard_default_generation_day_type
 * @property int $standard_default_generation_day_of_week
 * @property int $standard_default_delay_days
 * @property int $standard_default_payment_terms_days
 * @property bool $standard_default_auto_generate
 * @property bool $standard_default_auto_send
 */
class BillingSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'default_frequency',
        'default_generation_day_type',
        'default_generation_day_of_week',
        'default_delay_days',
        'default_payment_terms_days',
        'default_auto_generate',
        'default_auto_send',
        'advance_default_frequency',
        'advance_default_generation_day_type',
        'advance_default_generation_day_of_week',
        'advance_default_delay_days',
        'advance_default_payment_terms_days',
        'advance_default_auto_generate',
        'advance_default_auto_send',
        'standard_default_frequency',
        'standard_default_generation_day_type',
        'standard_default_generation_day_of_week',
        'standard_default_delay_days',
        'standard_default_payment_terms_days',
        'standard_default_auto_generate',
        'standard_default_auto_send',
        'reminder_days_before_due',
        'reminder_days_after_due',
        'reminder_overdue_repeat_days',
        'max_overdue_reminders',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_frequency' => BillingFrequency::class,
            'default_generation_day_type' => GenerationDayType::class,
            'default_generation_day_of_week' => 'integer',
            'default_delay_days' => 'integer',
            'default_payment_terms_days' => 'integer',
            'default_auto_generate' => 'boolean',
            'default_auto_send' => 'boolean',
            'advance_default_frequency' => BillingFrequency::class,
            'advance_default_generation_day_type' => GenerationDayType::class,
            'advance_default_generation_day_of_week' => 'integer',
            'advance_default_delay_days' => 'integer',
            'advance_default_payment_terms_days' => 'integer',
            'advance_default_auto_generate' => 'boolean',
            'advance_default_auto_send' => 'boolean',
            'standard_default_frequency' => BillingFrequency::class,
            'standard_default_generation_day_type' => GenerationDayType::class,
            'standard_default_generation_day_of_week' => 'integer',
            'standard_default_delay_days' => 'integer',
            'standard_default_payment_terms_days' => 'integer',
            'standard_default_auto_generate' => 'boolean',
            'standard_default_auto_send' => 'boolean',
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
