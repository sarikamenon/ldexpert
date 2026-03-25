<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceLineType: string
{
    case SESSION_CHARGE = 'session_charge';
    case ADVANCE_SCHEDULED = 'advance_scheduled';
    case ADJUST_NO_SHOW = 'adjust_no_show';
    case ADJUST_CANCEL_BILLABLE = 'adjust_cancel_billable';
    case ADJUST_CANCEL_NON_BILLABLE = 'adjust_cancel_non_billable';
    case ADJUST_EXTRA_SESSION = 'adjust_extra_session';
    case ADJUST_RATE_DIFFERENCE = 'adjust_rate_difference';
    case CARRY_FORWARD_CREDIT = 'carry_forward_credit';

    public function label(): string
    {
        return match ($this) {
            self::SESSION_CHARGE => 'Session Charge',
            self::ADVANCE_SCHEDULED => 'Advance Scheduled',
            self::ADJUST_NO_SHOW => 'Adjustment — No Show',
            self::ADJUST_CANCEL_BILLABLE => 'Adjustment — Billable Cancellation',
            self::ADJUST_CANCEL_NON_BILLABLE => 'Adjustment — Non-Billable Cancellation',
            self::ADJUST_EXTRA_SESSION => 'Adjustment — Extra Session',
            self::ADJUST_RATE_DIFFERENCE => 'Adjustment — Rate Difference',
            self::CARRY_FORWARD_CREDIT => 'Carry Forward Credit',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }

    public function isAdvanceCharge(): bool
    {
        return $this === self::ADVANCE_SCHEDULED;
    }

    public function isAdjustment(): bool
    {
        return match ($this) {
            self::ADJUST_NO_SHOW,
            self::ADJUST_CANCEL_BILLABLE,
            self::ADJUST_CANCEL_NON_BILLABLE,
            self::ADJUST_EXTRA_SESSION,
            self::ADJUST_RATE_DIFFERENCE,
            self::CARRY_FORWARD_CREDIT => true,
            default => false,
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::ADJUST_NO_SHOW,
            self::ADJUST_CANCEL_BILLABLE,
            self::ADJUST_CANCEL_NON_BILLABLE,
            self::CARRY_FORWARD_CREDIT => true,
            default => false,
        };
    }
}
