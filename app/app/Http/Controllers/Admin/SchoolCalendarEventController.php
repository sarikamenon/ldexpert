<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\School\Services\SchoolCalendarService;
use App\DTOs\CreateSchoolCalendarEventDTO;
use App\DTOs\UpdateSchoolCalendarEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolCalendarEvent\StoreSchoolCalendarEventRequest;
use App\Http\Requests\Admin\SchoolCalendarEvent\UpdateSchoolCalendarEventRequest;
use App\Http\Resources\Admin\SchoolCalendarEventCollection;
use App\Http\Resources\Admin\SchoolCalendarEventResource;
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

    public function index(Request $request, School $school): SchoolCalendarEventCollection
    {
        $this->authorize('viewAny', SchoolCalendarEvent::class);

        $start = $request->query('start')
            ? CarbonImmutable::parse((string) $request->query('start'))
            : CarbonImmutable::today()->startOfMonth();
        $end = $request->query('end')
            ? CarbonImmutable::parse((string) $request->query('end'))
            : CarbonImmutable::today()->endOfMonth();

        $events = $this->calendarService->listBySchoolAndRange($school->id, $start, $end);

        return new SchoolCalendarEventCollection($events->values());
    }

    public function store(StoreSchoolCalendarEventRequest $request, School $school): JsonResponse
    {
        $this->authorize('create', SchoolCalendarEvent::class);

        $payload = array_merge($request->validated(), [
            'school_id' => $school->id,
        ]);

        $event = $this->calendarService->create(CreateSchoolCalendarEventDTO::fromArray($payload));

        return SchoolCalendarEventResource::make($event)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSchoolCalendarEventRequest $request, School $school, SchoolCalendarEvent $event): SchoolCalendarEventResource
    {
        if ($event->school_id !== $school->id) {
            abort(404);
        }

        $this->authorize('update', $event);

        $updated = $this->calendarService->update($event, UpdateSchoolCalendarEventDTO::fromArray($request->validated()));

        return SchoolCalendarEventResource::make($updated);
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
