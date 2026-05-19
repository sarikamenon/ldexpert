<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ScheduleCalendarEventTransformer;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Therapist\Services\SessionLogService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\ScheduleFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleCalendarFilterRequest;
use App\Http\Resources\Schedule\ScheduleDetailsResource;
use App\Models\Schedule;
use App\Models\SessionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class ScheduleCalendarController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly TherapistService $therapistService,
        private readonly SessionLogService $sessionLogService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Schedule::class);

        $therapists = $this->therapistService->listActiveTherapists();

        return view('admin.schedule.calendar.index', [
            'therapists' => $therapists,
        ]);
    }

    public function events(ScheduleCalendarFilterRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Schedule::class);

        $validated = $request->validated();

        $filters = ScheduleFilterDTO::fromRequest([
            'therapist_ids' => $validated['therapist_ids'] ?? null,
            'student_id' => $validated['student_id'] ?? null,
            'school_id' => $validated['school_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'billing_status' => $validated['billing_status'] ?? null,
            'date_from' => $validated['start'],
            'date_to' => $validated['end'],
        ]);

        $schedules = $this->scheduleService->getSchedulesForCalendar($filters);

        // Admin viewer is neither original nor sub therapist; pass 0 so the
        // resolver returns null role/label and no coverage badge renders.
        $scheduleEvents = $schedules->map(
            static fn (Schedule $s): array => ScheduleCalendarEventTransformer::transform($s, 0)
        );

        // Orphan session logs (no schedule attached) — only when neither
        // status nor billing filter is applied (those are schedule-only fields).
        $events = $scheduleEvents;
        if (($validated['status'] ?? null) === null && ($validated['billing_status'] ?? null) === null) {
            $orphanLogs = $this->sessionLogService->getOrphanLogsForCalendar($filters);
            $orphanEvents = $orphanLogs->map(
                static fn (SessionLog $log): array => ScheduleCalendarEventTransformer::transformOrphanLog($log)
            );
            $events = $scheduleEvents->concat($orphanEvents);
        }

        return response()->json($events->values()->toArray());
    }

    public function show(int $id): ScheduleDetailsResource
    {
        /** @var Schedule $schedule */
        $schedule = Schedule::query()
            ->with([
                'therapist.therapistProfile',
                'student.studentProfile.school',
                'service',
                'ssa.primaryService',
                'school',
                'emailLogs.sentBy',
                'sessionLog',
                'subTherapist',
            ])
            ->findOrFail($id);

        $this->authorize('view', $schedule);

        return ScheduleDetailsResource::make($schedule)
            ->additional([
                'timezone' => $schedule->displayTimezone(),
                'session_log_route' => 'admin.session-logs.show',
            ]);
    }
}
