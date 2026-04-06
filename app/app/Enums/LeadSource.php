<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadSource: string
{
    case REFERRAL = 'referral';
    case WEBSITE = 'website';
    case SCHOOL = 'school';
    case EVENT = 'event';
    case PHONE = 'phone';
    case EMAIL = 'email';
    case SOCIAL_MEDIA = 'social_media';
    case WORD_OF_MOUTH = 'word_of_mouth';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::REFERRAL => 'Referral',
            self::WEBSITE => 'Website',
            self::SCHOOL => 'School/Family',
            self::EVENT => 'Event',
            self::PHONE => 'Phone',
            self::EMAIL => 'Email',
            self::SOCIAL_MEDIA => 'Social Media',
            self::WORD_OF_MOUTH => 'Word of Mouth',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $source): string => $source->value,
            self::cases()
        );
    }
}
