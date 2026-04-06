<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingScheduleType: string
{
    case SCHOOL_INVOICE = 'school_invoice';
    case THERAPIST_BILL = 'therapist_bill';

    public function label(): string
    {
        return match ($this) {
            self::SCHOOL_INVOICE => 'School/Family Invoice',
            self::THERAPIST_BILL => 'Therapist Bill',
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
}
