<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleMakeupEmailLogType: string
{
    case REMINDER = 'reminder';
    case THERAPIST_AVAILABILITY_REMINDER = 'therapist_availability_reminder';
    case THERAPIST_NO_AVAILABILITY_ACCEPTED = 'therapist_no_availability_accepted';
    case THERAPIST_DECLINED = 'therapist_declined';
    case THERAPIST_MAKEUP_SCHEDULED = 'therapist_makeup_scheduled';
    case THERAPIST_NON_ACCEPTED = 'therapist_non_accepted';

    public function label(): string
    {
        return match ($this) {
            self::REMINDER => 'Reminder',
            self::THERAPIST_AVAILABILITY_REMINDER => 'Therapist Availability Reminder',
            self::THERAPIST_NO_AVAILABILITY_ACCEPTED => 'Therapist No Availability (Accepted)',
            self::THERAPIST_DECLINED => 'Therapist Declined Notification',
            self::THERAPIST_MAKEUP_SCHEDULED => 'Therapist Make-Up Scheduled',
            self::THERAPIST_NON_ACCEPTED => 'Therapist Non-Accepted Notification',
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
