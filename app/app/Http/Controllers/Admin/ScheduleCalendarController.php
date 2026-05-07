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

        $scheduleEvents = $schedules->map(
            static fn (Schedule $s): array => ScheduleCalendarEventTransformer::transform($s)
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

    public function show(int $id): JsonResponse
    {
        /** @var Schedule $schedule */
        $schedule = Schedule::query()
            ->with([
                'student',
                'student.studentProfile',
                'student.studentProfile.school',
                'therapist',
                'service',
                'ssa',
                'ssa.primaryService',
                'school',
                'emailLogs.sentBy',
                'sessionLog',
            ])
            ->findOrFail($id);

        $this->authorize('view', $schedule);

        return response()->json([
            'schedule' => $this->buildScheduleResponse($schedule),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScheduleResponse(Schedule $schedule): array
    {
        $studentProfile = $schedule->student?->studentProfile;
        $ssa = $schedule->ssa;
        $durationMinutes = $schedule->durationMinutes();
        $tz = $schedule->displayTimezone();
        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);

        return [
            'id' => $schedule->id,
            'schedule_date' => $localStart->format('Y-m-d'),
            'schedule_date_formatted' => $localStart->format('M d, Y'),
            'start_time' => $localStart->format('H:i'),
            'start_time_formatted' => $localStart->format('g:i A'),
            'end_time' => $localEnd->format('H:i'),
            'end_time_formatted' => $localEnd->format('g:i A'),
            'duration_minutes' => $durationMinutes,
            'duration_formatted' => $this->formatDuration($durationMinutes),
            'status' => $schedule->status->value,
            'billing_status' => $schedule->billing_status->value,
            'notes' => $schedule->notes,
            'location_details' => $schedule->location_details,
            'is_past' => $localStart->lt(now($tz)->startOfDay()),
            'therapist' => [
                'id' => $schedule->therapist?->id,
                'name' => $schedule->therapist?->name,
            ],
            'service' => [
                'id' => $schedule->service?->id,
                'name' => $schedule->service?->name,
            ],
            'ssa' => $ssa ? [
                'id' => $ssa->id,
                'start_date_formatted' => $ssa->start_date->format('M d, Y'),
                'end_date_formatted' => $ssa->end_date?->format('M d, Y'),
                'minutes_per_session' => $ssa->minutes_per_session,
                'frequency' => $ssa->frequency?->value,
                'sessions_per_frequency' => $ssa->sessions_per_frequency,
                'status' => $ssa->status->value,
                'tho_minutes' => $ssa->tho_minutes ?? 0,
                'tho_hours' => $ssa->tho_hours,
                'served_minutes' => $ssa->served_minutes ?? 0,
                'served_hours' => $ssa->served_hours,
                'service' => [
                    'id' => $ssa->primaryService?->id,
                    'name' => $ssa->primaryService?->name,
                ],
            ] : null,
            'student' => [
                'id' => $schedule->student?->id,
                'name' => $schedule->student?->name,
                'email' => $schedule->student?->email,
                'id_number' => $studentProfile->id_number ?? '-',
                'timezone' => $studentProfile->timezone ?? '-',
            ],
            'school' => [
                'id' => $schedule->school?->id,
                'name' => $schedule->school->display_name ?? $schedule->school?->name,
            ],
            'parent' => [
                'name' => $studentProfile->parent_guardian_name ?? '-',
                'email' => $studentProfile->parent_guardian_email ?? '-',
                'phone' => $studentProfile->parent_guardian_phone ?? '-',
            ],
            'email_logs' => $schedule->emailLogs->sortByDesc('sent_at')->map(fn ($log) => [
                'sent_at' => $log->sent_at->copy()->setTimezone($tz)->format('M d, Y g:i A'),
                'type_label' => $log->type->label(),
                'type_value' => $log->type->value,
                'recipient_email' => $log->recipient_email,
                'sent_by' => $log->sentBy !== null ? $log->sentBy->name : 'System',
            ])->values()->toArray(),
            'session_log' => $schedule->sessionLog !== null ? [
                'id' => $schedule->sessionLog->id,
                'status' => $schedule->sessionLog->status?->value,
                'status_label' => $schedule->sessionLog->status?->label(),
                'url' => route('admin.session-logs.show', $schedule->sessionLog),
            ] : null,
        ];
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$mins}m";
    }
}
