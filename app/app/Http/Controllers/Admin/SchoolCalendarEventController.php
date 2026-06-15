<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\School\Services\SchoolCalendarService;
use App\DTOs\CreateSchoolCalendarEventDTO;
use App\DTOs\UpdateSchoolCalendarEventDTO;
use App\Enums\SchoolCalendarEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolCalendarEvent\StoreSchoolCalendarEventRequest;
use App\Http\Requests\Admin\SchoolCalendarEvent\UpdateSchoolCalendarEventRequest;
use App\Models\School;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolCalendarEventController extends Controller
{
    public function __construct(
        private readonly SchoolCalendarService $calendarService,
    ) {}

    public function index(Request $request, School $school): JsonResponse
    {
        $this->authorize('viewAny', SchoolCalendarEvent::class);

        $start = $request->query('start')
            ? CarbonImmutable::parse((string) $request->query('start'))
            : CarbonImmutable::today()->startOfMonth();
        $end = $request->query('end')
            ? CarbonImmutable::parse((string) $request->query('end'))
            : CarbonImmutable::today()->endOfMonth();

        $events = $this->calendarService->listBySchoolAndRange($school->id, $start, $end);

        return response()->json([
            'events' => $events->map(function (SchoolCalendarEvent $event): array {
                return [
                    'id' => $event->id,
                    'school_id' => $event->school_id,
                    'title' => $event->title,
                    'event_type' => $event->event_type->value,
                    'event_type_label' => $event->event_type->label(),
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date->format('Y-m-d'),
                    'notes' => $event->notes,
                    'is_holiday' => $event->event_type === SchoolCalendarEventType::HOLIDAY,
                ];
            })->values(),
        ]);
    }

    public function store(StoreSchoolCalendarEventRequest $request, School $school): JsonResponse
    {
        $this->authorize('create', SchoolCalendarEvent::class);

        $payload = array_merge($request->validated(), [
            'school_id' => $school->id,
        ]);

        $event = $this->calendarService->create(CreateSchoolCalendarEventDTO::fromArray($payload));

        return response()->json([
            'event' => [
                'id' => $event->id,
                'school_id' => $event->school_id,
                'title' => $event->title,
                'event_type' => $event->event_type->value,
                'event_type_label' => $event->event_type->label(),
                'start_date' => $event->start_date->format('Y-m-d'),
                'end_date' => $event->end_date->format('Y-m-d'),
                'notes' => $event->notes,
                'is_holiday' => $event->event_type === SchoolCalendarEventType::HOLIDAY,
            ],
        ], 201);
    }

    public function update(UpdateSchoolCalendarEventRequest $request, School $school, SchoolCalendarEvent $event): JsonResponse
    {
        if ($event->school_id !== $school->id) {
            abort(404);
        }

        $this->authorize('update', $event);

        $updated = $this->calendarService->update($event, UpdateSchoolCalendarEventDTO::fromArray($request->validated()));

        return response()->json([
            'event' => [
                'id' => $updated->id,
                'school_id' => $updated->school_id,
                'title' => $updated->title,
                'event_type' => $updated->event_type->value,
                'event_type_label' => $updated->event_type->label(),
                'start_date' => $updated->start_date->format('Y-m-d'),
                'end_date' => $updated->end_date->format('Y-m-d'),
                'notes' => $updated->notes,
                'is_holiday' => $updated->event_type === SchoolCalendarEventType::HOLIDAY,
            ],
        ]);
    }

    public function destroy(Request $request, School $school, SchoolCalendarEvent $event): JsonResponse
    {
        if ($event->school_id !== $school->id) {
            abort(404);
        }

        $this->authorize('delete', $event);

        $this->calendarService->delete($event);

        return response()->json([
            'success' => true,
        ]);
    }
}
