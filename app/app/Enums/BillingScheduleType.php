<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingScheduleType: string
{
    case SCHOOL_INVOICE = 'school_invoice';
    case PRIVATE_STUDENT_INVOICE = 'private_student_invoice';
    case THERAPIST_BILL = 'therapist_bill';

    public function label(): string
    {
        return match ($this) {
            self::SCHOOL_INVOICE => 'School Invoice',
            self::PRIVATE_STUDENT_INVOICE => 'Private Student Invoice',
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
