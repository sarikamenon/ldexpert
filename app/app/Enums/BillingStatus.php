<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingStatus: string
{
    case PENDING = 'pending';
    case BILLED = 'billed';
    case NOT_BILLABLE = 'not_billable';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::BILLED => 'Billed',
            self::NOT_BILLABLE => 'Not Billable',
            self::WAIVED => 'Waived',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn(self $status): string => $status->value,
            self::cases()
        );
    }
}

