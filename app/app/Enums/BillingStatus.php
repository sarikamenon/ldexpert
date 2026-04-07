<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingStatus: string
{
    case PENDING = 'pending';
    case BILLED = 'billed';
    case NOT_BILLABLE = 'not_billable';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::BILLED => 'Billed',
            self::NOT_BILLABLE => 'Not Billable',
        };
    }

    /**
     * Calendar event colour for this billing status within a given schedule status.
     * Cancelled schedules never reach here — the caller handles that case first.
     *
     * Hex values are required: FullCalendar's JS API accepts only CSS colour strings,
     * not Tailwind utility classes. Keep these in sync with the design system palette.
     */
    public function calendarColor(ScheduleStatus $scheduleStatus): string
    {
        return match ($scheduleStatus) {
            ScheduleStatus::COMPLETED => match ($this) {
                self::BILLED       => '#059669', // success-600
                self::PENDING      => '#d97706', // warning-600
                self::NOT_BILLABLE => '#6b7280', // foreground/40
            },
            default => match ($this) {
                self::BILLED       => '#10b981', // success-500
                self::PENDING      => '#5563b8', // primary
                self::NOT_BILLABLE => '#94a3b8', // foreground/30
            },
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
