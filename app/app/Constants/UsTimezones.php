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

    public static function getTimezones(): array
    {
        return self::TIMEZONES;
    }

    public static function getTimezoneLabel(string $timezone): string
    {
        return self::TIMEZONES[$timezone] ?? $timezone;
    }

    /**
     * Resolve timezone from CSV input. Accepts both timezone key (e.g. America/New_York)
     * and display label (e.g. Eastern Time (ET)) for better UX.
     */
    public static function resolveFromInput(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $input = trim($input);

        if (array_key_exists($input, self::TIMEZONES)) {
            return $input;
        }

        $key = array_search($input, self::TIMEZONES, true);

        return $key !== false ? $key : null;
    }
}
