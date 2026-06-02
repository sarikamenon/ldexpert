<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\Schedule;
use App\Models\ScheduleMakeupAvailability;
use Illuminate\Support\Collection;

final class MakeupAvailabilityRowTransformer
{
    /**
     * @param  Collection<int, Schedule>  $bookedSchedules
     * @return array{id: int, date: string, start: string, end: string, notes: string|null, booked_slots: array<int, string>, delete_url: string}
     */
    public static function transform(ScheduleMakeupAvailability $window, string $tz, Collection $bookedSchedules): array
    {
        $start = $window->startUtc()->setTimezone($tz);
        $end = $window->endUtc()->setTimezone($tz);

        $timeFmt = config('display.time');
        $bookedSlots = $bookedSchedules
            ->map(fn (Schedule $s): string =>
                $s->startUtc()->setTimezone($tz)->format($timeFmt)
                .' – '
                .$s->endUtc()->setTimezone($tz)->format($timeFmt)
            )
            ->values()
            ->all();

        return [
            'id' => (int) $window->id,
            'date' => $start->format(config('display.date')),
            'start' => $start->format($timeFmt),
            'end' => $end->format($timeFmt),
            'notes' => $window->notes,
            'booked_slots' => $bookedSlots,
            'delete_url' => route('therapist.makeup-requests.availability.destroy', $window),
        ];
    }
}
