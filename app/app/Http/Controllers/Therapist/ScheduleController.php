<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Constants\UsTimezones;
use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Therapist\Services\SessionLogService;
use App\Domain\Time\UserTimezoneService;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\ServiceStatus;
use App\Exceptions\CannotDeleteBilledScheduleException;
use App\Exceptions\ScheduleOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\ScheduleFilterRequest;
use App\Http\Requests\Therapist\StoreScheduleRequest;
use App\Http\Requests\Therapist\UpdateScheduleRequest;
use App\Http\Resources\Schedule\ScheduleDetailsResource;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSAService $ssaService,
        private readonly SessionLogService $sessionLogService,
        private readonly SchoolCalendarService $calendarService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $selectedDate = $request->query('date')
            ? CarbonImmutable::parse($request->query('date'))
            : CarbonImmutable::today();

        $ssaId = $request->query('ssa_id');
        // SSA is now required to create a schedule. If we don't have a valid SSA,
        // redirect back to the calendar with a message instructing the user to
        // start from the "Add New Schedule" flow.
        if (! $ssaId) {
            return redirect()
                ->route('therapist.schedule-calendar.index')
                ->with('status', 'Please click the "Add New Schedule" button and select an SSA to create a schedule.');
        }

        $ssa = $this->ssaService->findSSAForSchedule((int) $ssaId, $therapist->id);

        if (! $ssa) {
            return redirect()
                ->route('therapist.schedule-calendar.index')
                ->with('status', 'Please click the "Add New Schedule" button and select an SSA to create a schedule.');
        }

        $student = $ssa->student;
        $service = $ssa->primaryService;

        // Build student list and service mappings using the selected SSA only
        $students = collect([
            (object) [
                'user_id' => $student?->id,
                'first_name' => $student->name ?? '',
                'last_name' => '',
            ],
        ])->filter(static fn ($studentInfo) => $studentInfo->user_id !== null)->values();

        // Get primary service from SSA
        $ssaServices = $ssa->services()->where('status', ServiceStatus::ACTIVE)->get();
        $serviceOptions = $ssaServices->map(function (Service $service) {
            /** @var \App\Models\Pivots\SSAService|null $pivot */
            $pivot = $service->getRelation('pivot');

            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'is_primary' => (bool) $pivot?->is_primary,
                'is_direct_service' => $service->is_direct_service,
            ];
        })->values();

        // Append common indirect services available to both the therapist and student's school
        $schoolId = $student?->studentProfile?->school_id;
        $therapistProfileId = $therapist->therapistProfile?->id;
        if ($schoolId && $therapistProfileId) {
            $existingIds = $serviceOptions->pluck('service_id')->all();
            $indirectOptions = $this->serviceCatalogService
                ->listCommonIndirectServices($therapistProfileId, $schoolId)
                ->reject(static fn (Service $s) => in_array($s->id, $existingIds, true))
                ->map(static fn (Service $s) => [
                    'service_id' => $s->id,
                    'service_name' => $s->name,
                    'is_primary' => false,
                    'is_direct_service' => $s->is_direct_service,
                ]);
            $serviceOptions = $serviceOptions->merge($indirectOptions)->values();
        }

        $studentServiceMappings = collect([
            [
                'student_id' => $ssa->student_id,
                'services' => $serviceOptions->toArray(),
            ],
        ]);

        $therapistTimezone = $therapist->therapistProfile->timezone ?? 'America/Chicago';
        $therapistTimezoneLabel = UsTimezones::getTimezoneLabel($therapistTimezone);

        $isPrivateStudent = $student?->studentProfile?->school?->is_private_student === true;

        return view('therapist.schedule.create', [
            'selectedDate' => $selectedDate,
            'students' => $students,
            'schools' => $this->scheduleService->getSchools($therapist),
            'studentServiceMappings' => $studentServiceMappings,
            'serviceOptions' => $serviceOptions,
            'ssa' => $ssa,
            'preselectedStudent' => $student,
            'preselectedService' => $service,
            'therapistTimezone' => $therapistTimezone,
            'therapistTimezoneLabel' => $therapistTimezoneLabel,
            'isPrivateStudent' => $isPrivateStudent,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        /** @var User $therapist */
        $therapist = $request->user();
        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $schedule->load([
            'student',
            'student.studentProfile',
            'student.studentProfile.school',
            'service',
            'ssa',
            'ssa.primaryService',
            'ssa.student',
            'ssa.student.studentProfile',
            'ssa.student.studentProfile.school',
            'school',
        ]);

        $this->authorize('update', $schedule);

        $therapistTimezone = $therapist->therapistProfile->timezone ?? 'America/Chicago';
        $therapistTimezoneLabel = UsTimezones::getTimezoneLabel($therapistTimezone);

        $isPrivateStudent = $schedule->student?->studentProfile?->school?->is_private_student === true;

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);

        return view('therapist.schedule.edit', [
            'schedule' => $schedule,
            'therapistTimezone' => $therapistTimezone,
            'therapistTimezoneLabel' => $therapistTimezoneLabel,
            'isPrivateStudent' => $isPrivateStudent,
            'scheduleLocalDate' => $localStart->format('Y-m-d'),
            'scheduleLocalDateFormatted' => $localStart->format('M d, Y'),
            'scheduleLocalStartTime' => $localStart->format('H:i'),
            'scheduleLocalEndTime' => $localEnd->format('H:i'),
        ]);
    }

    public function getSchedules(ScheduleFilterRequest $request): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();
        $filters = ScheduleFilterDTO::fromRequest($request->validated());

        $schedules = $this->scheduleService->getSchedules($therapist, $filters);

        $schools = $this->scheduleService->getSchools($therapist);
        $schoolIds = $filters->schoolId
            ? [(int) $filters->schoolId]
            : $schools->pluck('id')->map('intval')->toArray();
        $selectedDate = $filters->date
            ? CarbonImmutable::parse($filters->date)
            : CarbonImmutable::today();
        $events = $this->calendarService
            ->listBySchoolsAndRange($schoolIds, $selectedDate->startOfDay(), $selectedDate->endOfDay())
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'school_id' => $event->school_id,
                    'title' => $event->title,
                    'event_type' => $event->event_type->value,
                    'event_type_label' => $event->event_type->label(),
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date->format('Y-m-d'),
                    'notes' => $event->notes,
                    'is_holiday' => $event->event_type->value === 'holiday',
                ];
            })->values();

        $sessionLogsBySchedule = $this->sessionLogService->getSessionLogsByScheduleIds(
            $therapist,
            $schedules->pluck('id')->toArray()
        );

        $tz = $this->timezoneService->resolveTimezone($therapist);

        return response()->json([
            'schedules' => $schedules->map(function ($schedule) use ($sessionLogsBySchedule, $tz) {
                $localStart = $schedule->localStart($tz);
                $localEnd = $schedule->localEnd($tz);
                $isPast = $localStart->lt(now($tz)->startOfDay());
                $hasEventStarted = now()->gte($schedule->startUtc());
                $isBilled = $schedule->billing_status === BillingStatus::BILLED;
                $isPendingBilling = $schedule->billing_status === BillingStatus::PENDING;
                /** @var \App\Models\SessionLog|null $sessionLog */
                $sessionLog = $sessionLogsBySchedule->get($schedule->id)?->first();

                return [
                    'id' => $schedule->id,
                    'schedule_date' => $localStart->format('Y-m-d'),
                    'start_time' => $localStart->format('H:i'),
                    'end_time' => $localEnd->format('H:i'),
                    'school' => $schedule->school?->display_name,
                    'student' => $schedule->student?->name,
                    'service' => $schedule->service?->name,
                    'status' => $schedule->status->value,
                    'billing_status' => $schedule->billing_status->value,
                    'is_group' => $schedule->is_group,
                    'is_past' => $isPast,
                    'has_event_started' => $hasEventStarted,
                    'is_billed' => $isBilled,
                    'bill_url' => $hasEventStarted && $isPendingBilling
                        ? route('therapist.session-logs.create.from-schedule', $schedule->id)
                        : null,
                    'session_log_url' => $isPast && $isBilled && $sessionLog
                        ? route('therapist.session-logs.show', $sessionLog)
                        : null,
                    'notes' => $schedule->notes,
                    'location_details' => $schedule->location_details,
                    'student_name' => $schedule->student?->name,
                    'student_password' => $schedule->student?->studentProfile->id_number ?? '-',
                    'parent_name' => $schedule->student?->studentProfile->parent_guardian_name ?? '-',
                    'parent_email' => $schedule->student?->studentProfile->parent_guardian_email ?? '-',
                    'parent_phone' => $schedule->student?->studentProfile->parent_guardian_phone ?? '-',
                    'edit_url' => route('therapist.schedule.edit', $schedule->id),
                ];
            })->toArray(),
            'events' => $events,
        ]);
    }

    public function pending(ScheduleFilterRequest $request): View
    {
        /** @var User $therapist */
        $therapist = $request->user();
        $pendingCount = $this->scheduleService->getPendingCount($therapist);

        $filters = ScheduleFilterDTO::fromRequest($request->validated());
        $pendingSchedules = $this->scheduleService->getPendingSchedules($therapist, $filters);

        // Reference data for filters
        $ssas = $this->ssaService
            ->getActiveSSAsForTherapist($therapist->id)
            ->loadMissing(['student.studentProfile', 'services']);

        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $students */
        $students = $ssas
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        /** @var \Illuminate\Support\Collection<int, \App\Models\Service> $services */
        $services = $ssas
            ->flatMap(function ($ssa) {
                $allServices = collect([$ssa->primaryService])->filter();
                $allServices = $allServices->merge($ssa->services);

                return $allServices;
            })
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('therapist.schedule.pending', [
            'pendingSchedules' => $pendingSchedules,
            'pendingCount' => $pendingCount,
            'students' => $students,
            'ssas' => $ssas,
            'services' => $services,
            'filters' => $filters->toArray(),
        ]);
    }

    public function store(StoreScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        /** @var User $therapist */
        $therapist = $request->user();

        $data = $request->validated();
        $data['therapist_id'] = $therapist->id;
        $data['is_group'] = count($data['student_ids'] ?? []) > 1;

        $dto = CreateScheduleDTO::fromArray($data);

        try {
            $schedule = $this->scheduleService->createSchedule($therapist, $dto)
                ->load(['student', 'service', 'ssa', 'school']);
        } catch (ScheduleOverlapException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Schedule conflict detected.',
                    'errors' => [
                        'start_time' => [$e->getMessage()],
                    ],
                ], 422);
            }

            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'service_id' => [$e->getMessage()],
                    ],
                ], 422);
            }

            return back()->withErrors(['service_id' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'schedule' => $schedule,
            ], 201);
        }

        return redirect()
            ->route('therapist.schedule-calendar.index')
            ->with('status', 'Schedule created successfully.');
    }

    public function update(UpdateScheduleRequest $request, int $id): JsonResponse|RedirectResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();
        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $this->authorize('update', $schedule);

        $dto = UpdateScheduleDTO::fromArray($request->validated());

        try {
            $updated = $this->scheduleService->updateSchedule($therapist, $id, $dto)
                ->load(['student', 'service', 'ssa', 'school']);
        } catch (ScheduleOverlapException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Schedule conflict detected.',
                    'errors' => [
                        'start_time' => [$e->getMessage()],
                    ],
                ], 422);
            }

            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'schedule' => $updated,
            ]);
        }

        return redirect()
            ->route('therapist.schedule-calendar.index')
            ->with('status', 'Schedule updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $this->authorize('delete', $schedule);

        try {
            $this->scheduleService->deleteSchedule($therapist, $id);
        } catch (CannotDeleteBilledScheduleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroyFutureRecurring(Request $request, int $id): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $this->authorize('delete', $schedule);

        if (! $schedule->recurring_batch_number) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule is not part of a recurring series.',
            ], 422);
        }

        $deletedCount = $this->scheduleService->deleteFutureRecurringSchedules($therapist, $id);

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
        ]);
    }

    public function removeStudent(Request $request, int $id): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $this->authorize('delete', $schedule);

        $this->scheduleService->removeStudentFromOccurrence($therapist, $id);

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateBillingStatus(Request $request, int $id): JsonResponse
    {
        /** @var User $therapist */
        $therapist = $request->user();

        $schedule = $this->scheduleService->findForTherapist($therapist, $id);

        if (! $schedule) {
            abort(404);
        }

        $this->authorize('updateBillingStatus', $schedule);

        $billingStatuses = array_map(
            static fn (BillingStatus $status): string => $status->value,
            BillingStatus::cases()
        );

        $validated = $request->validate([
            'billing_status' => ['required', 'string', Rule::in($billingStatuses)],
        ]);

        $status = BillingStatus::from($validated['billing_status']);

        $updated = $this->scheduleService->updateBillingStatus($therapist, $id, $status)
            ->load(['student', 'service', 'ssa', 'school']);

        return response()->json([
            'schedule' => $updated,
        ]);
    }

    public function bulkUpdateBillingStatus(Request $request): JsonResponse
    {
        $billingStatuses = array_map(
            static fn (BillingStatus $status): string => $status->value,
            BillingStatus::cases()
        );

        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['required', 'integer'],
            'billing_status' => ['required', 'string', Rule::in($billingStatuses)],
        ]);

        /** @var User $therapist */
        $therapist = $request->user();

        $status = BillingStatus::from($validated['billing_status']);

        $updatedCount = $this->scheduleService->bulkUpdateBillingStatus(
            $therapist,
            array_map('intval', $validated['schedule_ids']),
            $status
        );

        return response()->json([
            'updated' => $updatedCount,
        ]);
    }

    public function show(Request $request, int $id): ScheduleDetailsResource
    {
        /** @var User $therapist */
        $therapist = $request->user();
        $schedule = $this->scheduleService->findForTherapistWithRelations($therapist, $id, [
            'therapist.therapistProfile',
            'student.studentProfile.school',
            'service',
            'ssa.primaryService',
            'school',
            'emailLogs.sentBy',
            'sessionLog',
        ]);

        if ($schedule === null) {
            abort(404);
        }

        $this->authorize('view', $schedule);

        $tz = $this->timezoneService->resolveTimezone($schedule->therapist ?? $therapist);

        return ScheduleDetailsResource::make($schedule)
            ->additional([
                'timezone' => $tz,
                'session_log_route' => 'therapist.session-logs.show',
            ]);
    }
}
