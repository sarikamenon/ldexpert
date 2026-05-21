<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SchoolCalendarEventType;
use App\Models\SchoolCalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SchoolCalendarEvent $resource
 */
class SchoolCalendarEventResource extends JsonResource
{
    public static $wrap = 'event';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            'school_id' => $event->school_id,
            'title' => $event->title,
            'event_type' => $event->event_type->value,
            'event_type_label' => $event->event_type->label(),
            'start_date' => $event->start_date->format('Y-m-d'),
            'end_date' => $event->end_date->format('Y-m-d'),
            'reminder_date' => $event->reminder_date?->format('Y-m-d'),
            'response_date' => $event->response_date?->format('Y-m-d'),
            'deadline_date' => $event->deadline_date?->format('Y-m-d'),
            'notes' => $event->notes,
            'is_holiday' => $event->event_type === SchoolCalendarEventType::HOLIDAY,
        ];
    }
}
