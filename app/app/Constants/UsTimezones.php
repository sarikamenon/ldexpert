<?php

declare(strict_types=1);

namespace App\Constants;

class UsTimezones
{
    public const TIMEZONES = [
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Phoenix' => 'Mountain Time - Arizona (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'America/Anchorage' => 'Alaska Time (AKT)',
        'America/Adak' => 'Hawaii-Aleutian Time (HAT)',
        'Pacific/Honolulu' => 'Hawaii Time (HT)',
    ];

    /**
     * @return array<string, string>
     */
    public static function getTimezones(): array
    {
        return self::TIMEZONES;
    }

    public static function getTimezoneLabel(string $timezone): string
    {
        return self::TIMEZONES[$timezone] ?? $timezone;
    }
}
