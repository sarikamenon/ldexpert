<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case PROGRESS_REPORT = 'progress_report';
    case IEP = 'iep';
    case CONSENT_FORM = 'consent_form';
    case ASSESSMENT = 'assessment';
    case COMMON = 'common';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PROGRESS_REPORT => 'Progress Report',
            self::IEP => 'IEP',
            self::CONSENT_FORM => 'Consent Form',
            self::ASSESSMENT => 'Assessment',
            self::COMMON => 'Common Document',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $type): string => $type->value,
            self::cases()
        );
    }
}
