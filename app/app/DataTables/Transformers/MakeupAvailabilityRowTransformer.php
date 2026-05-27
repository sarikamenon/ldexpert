<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ScheduleMakeupAvailability;
use Carbon\Carbon;

final class MakeupAvailabilityRowTransformer
{
    /**
     * @return array{id: int, date: string, start: string, end: string, notes: string|null, delete_url: string}
     */
    public static function transform(ScheduleMakeupAvailability $window, string $tz): array
    {
        $dateStr = $window->availability_date->toDateString();
        $start = Carbon::parse($dateStr.' '.$window->start_time->format('H:i').':00', 'UTC')->setTimezone($tz);
        $end = Carbon::parse($dateStr.' '.$window->end_time->format('H:i').':00', 'UTC')->setTimezone($tz);

        return [
            'id' => (int) $window->id,
            'date' => $start->format(config('display.date')),
            'start' => $start->format(config('display.time')),
            'end' => $end->format(config('display.time')),
            'notes' => $window->notes,
            'delete_url' => route('therapist.makeup-requests.availability.destroy', $window),
        ];
    }
}
