<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\ScheduleCalendarEventTransformer;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Therapist\Services\ScheduleService;
use App\DTOs\ScheduleFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\ScheduleCalendarEventsRequest;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ScheduleCalendarController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSAService $ssaService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Schedule::class);

        /** @var User $therapist */
        $therapist = $request->user();

        $students = $this->ssaService->getUniqueStudentsForTherapist($therapist->id);

        return view('therapist.schedule.fullcalendar', [
            'students' => $students,
        ]);
    }

    public function events(ScheduleCalendarEventsRequest $request): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $validated = $request->validated();

        $filters = ScheduleFilterDTO::fromRequest([
            'therapist_id' => $therapist->id,
            'student_ids' => $validated['student_ids'] ?? null,
            'school_id' => $validated['school_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'billing_status' => $validated['billing_status'] ?? null,
            'date_from' => $validated['start'],
            'date_to' => $validated['end'],
        ]);

        $schedules = $this->scheduleService->getSchedulesForCalendar($filters);

        $events = $schedules->map(
            static fn (Schedule $s): array => ScheduleCalendarEventTransformer::transform($s)
        )->values()->toArray();

        return response()->json($events);
    }
}
