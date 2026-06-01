<?php

declare(strict_types=1);

namespace App\Constants;

use Illuminate\Support\Carbon;

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
        'Asia/Karachi' => 'Islamabad, Karachi',
        'Europe/Istanbul' => 'Istanbul',
        'Europe/London' => 'Edinburgh, London',
        'Europe/Dublin' => 'Dublin',
        'Europe/Lisbon' => 'Lisbon',
    ];

    /**
     * International zones whose label carries a live, DST-aware UTC offset
     * (e.g. "Edinburgh, London (UTC+01:00)"). The US zones keep their fixed
     * abbreviation labels above.
     *
     * @var list<string>
     */
    private const OFFSET_LABELLED = [
        'Asia/Karachi',
        'Europe/Istanbul',
        'Europe/London',
        'Europe/Dublin',
        'Europe/Lisbon',
    ];

    /**
     * @return array<string, string>
     */
    public static function getTimezones(): array
    {
        $timezones = self::TIMEZONES;

        foreach (array_keys($timezones) as $timezone) {
            $timezones[$timezone] = self::getTimezoneLabel($timezone);
        }

        return $timezones;
    }

    public static function getTimezoneLabel(string $timezone): string
    {
        $label = self::TIMEZONES[$timezone] ?? $timezone;

        if (! in_array($timezone, self::OFFSET_LABELLED, true)) {
            return $label;
        }

        $offset = Carbon::now($timezone)->format('P');

        return "{$label} (UTC{$offset})";
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
