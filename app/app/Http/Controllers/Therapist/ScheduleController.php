<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Constants\UsTimezones;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Domain\Schedule\Sub\Presenters\SubCoveragePanelPresenter;
use App\Domain\Schedule\Sub\Services\CoverageRoleResolver;
use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
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
use App\Enums\ScheduleSubCoverageStatus;
use App\Enums\ServiceStatus;
use App\Enums\SubRequestInviteeStatus;
use App\Enums\WeekDay;
use App\Exceptions\CannotDeleteBilledScheduleException;
use App\Exceptions\ScheduleOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\ScheduleFilterRequest;
use App\Http\Requests\Therapist\StoreScheduleRequest;
use App\Http\Requests\Therapist\UpdateScheduleRequest;
use App\Http\Resources\Schedule\ScheduleDetailsResource;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSAService $ssaService,
        private readonly SessionLogService $sessionLogService,
        private readonly SchoolCalendarService $calendarService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly UserTimezoneService $timezoneService,
        private readonly ScheduleSubRequestService $subRequestService,
        private readonly SubCoveragePanelPresenter $subCoveragePanelPresenter,
        private readonly ScheduleMakeupRequestRepositoryInterface $makeupRequestRepository,
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
        $allowsWeekendScheduling = $student?->studentProfile?->school?->allow_weekend_scheduling === true;
        $weekDays = collect(WeekDay::cases())
            ->reject(static fn (WeekDay $day): bool => $day->isWeekend() && ! $allowsWeekendScheduling)
            ->values()
            ->all();

        $holidayDates = $schoolId ? $this->resolveUpcomingHolidayDates((int) $schoolId) : [];

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
            'allowsWeekendScheduling' => $allowsWeekendScheduling,
            'weekDays' => $weekDays,
            'holidayDates' => $holidayDates,
            'makeupRequestId' => $request->query('makeup_request_id') !== null
                ? (int) $request->query('makeup_request_id')
                : null,
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
            'activeSubRequest.invitees.therapist',
            'activeSubRequest.acceptedBy',
        ]);

        $this->authorize('update', $schedule);

        $therapistTimezone = $therapist->therapistProfile->timezone ?? 'America/Chicago';
        $therapistTimezoneLabel = UsTimezones::getTimezoneLabel($therapistTimezone);

        $isPrivateStudent = $schedule->student?->studentProfile?->school?->is_private_student === true;
        $allowsWeekendScheduling = $schedule->student?->studentProfile?->school?->allow_weekend_scheduling === true;
        $weekDays = collect(WeekDay::cases())
            ->reject(static fn (WeekDay $day): bool => $day->isWeekend() && ! $allowsWeekendScheduling)
            ->values()
            ->all();

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);

        $subPanel = $this->subCoveragePanelPresenter->present($schedule, $tz);

        $editSchoolId = $schedule->school_id
            ?? $schedule->student?->studentProfile?->school_id;
        $holidayDates = $editSchoolId ? $this->resolveUpcomingHolidayDates((int) $editSchoolId) : [];

        return view('therapist.schedule.edit', [
            'schedule' => $schedule,
            'therapistTimezone' => $therapistTimezone,
            'therapistTimezoneLabel' => $therapistTimezoneLabel,
            'isPrivateStudent' => $isPrivateStudent,
            'allowsWeekendScheduling' => $allowsWeekendScheduling,
            'weekDays' => $weekDays,
            'holidayDates' => $holidayDates,
            'scheduleLocalDate' => $localStart->format('Y-m-d'),
            'scheduleLocalDateFormatted' => $localStart->format('M d, Y'),
            'scheduleLocalStartTime' => $localStart->format('H:i'),
            'scheduleLocalEndTime' => $localEnd->format('H:i'),
            'subPanel' => $subPanel,
            'makeupRequestId' => $request->query('makeup_request_id') !== null
                ? (int) $request->query('makeup_request_id')
                : null,
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
            'schedules' => $schedules->map(function ($schedule) use ($sessionLogsBySchedule, $tz, $therapist) {
                $localStart = $schedule->localStart($tz);
                $localEnd = $schedule->localEnd($tz);
                $isPast = $localStart->lt(now($tz)->startOfDay());
                $hasEventStarted = now()->gte($schedule->startUtc());
                $isBilled = $schedule->billing_status === BillingStatus::BILLED;
                $isPendingBilling = $schedule->billing_status === BillingStatus::PENDING;
                /** @var \App\Models\SessionLog|null $sessionLog */
                $sessionLog = $sessionLogsBySchedule->get($schedule->id)?->first();

                $coverage = CoverageRoleResolver::for($schedule, (int) $therapist->id);
                $coverageRole = $coverage['role'];
                $coverageLabel = $coverage['badge_label'];

                // When the original therapist's schedule is now covered by a sub, they
                // should not bill / log it — the sub will. Suppress billing affordance.
                $canBill = $hasEventStarted && $isPendingBilling && $coverageRole !== 'covered';

                // Subs covering someone else's schedule cannot edit/delete it — they're just delivering the session.
                $canEditOrDelete = ! $isBilled && $coverageRole !== 'covering';

                return [
                    'id' => $schedule->id,
                    'schedule_date' => $localStart->format('Y-m-d'),
                    'start_time' => $localStart->format('H:i'),
                    'start_time_formatted' => $localStart->format(config('display.time')),
                    'start_time_display' => $localStart->format(config('display.time')),
                    'end_time' => $localEnd->format('H:i'),
                    'end_time_formatted' => $localEnd->format(config('display.time')),
                    'end_time_display' => $localEnd->format(config('display.time')),
                    'school' => $schedule->school?->display_name,
                    'student' => $schedule->student?->name,
                    'service' => $schedule->service?->name,
                    'status' => $schedule->status->value,
                    'billing_status' => $schedule->billing_status->value,
                    'is_group' => $schedule->is_group,
                    'is_past' => $isPast,
                    'has_event_started' => $hasEventStarted,
                    'is_billed' => $isBilled,
                    'is_pending_billing' => $isPendingBilling,
                    'can_edit_or_delete' => $canEditOrDelete,
                    'bill_url' => $canBill
                        ? route('therapist.session-logs.create.from-schedule', $schedule->id)
                        : null,
                    'coverage_role' => $coverageRole,
                    'coverage_badge_label' => $coverageLabel,
                    'coverage_badge_classes' => CoverageRoleResolver::badgeClassesFor($coverageRole),
                    'sub_request_status' => $schedule->sub_request_status?->value,
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
        $pendingSchedules->loadMissing('activeSubRequest.invitees');

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
            'subRequestRequestedStatus' => ScheduleSubCoverageStatus::REQUESTED,
            'inviteeInvitedStatus' => SubRequestInviteeStatus::INVITED,
            'inviteeDeclinedStatus' => SubRequestInviteeStatus::DECLINED,
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

        $subWarning = null;
        if ($request->boolean('request_sub')) {
            /** @var array<int, int> $subInviteeIds */
            $subInviteeIds = array_map('intval', (array) $request->input('sub_invitee_ids', []));
            $subReason = $request->string('sub_reason')->toString() ?: null;
            $subWarning = $this->raiseSubRequestForNewSchedule($therapist, $schedule, $subInviteeIds, $subReason);
        }

        $makeupRequestId = $request->input('makeup_request_id');
        if ($makeupRequestId !== null) {
            $this->linkMakeupRequestSchedule($therapist, (int) $makeupRequestId, $schedule->id);
        }

        if ($request->expectsJson()) {
            $response = ['schedule' => $schedule];
            if ($subWarning !== null) {
                $response['sub_warning'] = $subWarning;
            }

            return response()->json($response, 201);
        }

        if ($subWarning !== null) {
            return redirect()
                ->route('therapist.schedule.edit', $schedule->id)
                ->with('status', 'Schedule created successfully.')
                ->with('warning', $subWarning);
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
                ->load(['student', 'service', 'ssa', 'school', 'activeSubRequest']);
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

        $makeupRequestId = $request->input('makeup_request_id');
        if ($makeupRequestId !== null) {
            $this->linkMakeupRequestSchedule($therapist, (int) $makeupRequestId, $updated->id);
        }

        $subWarning = null;
        if ($request->has('sub_invitee_ids') || $request->boolean('request_sub')) {
            /** @var array<int, int> $inviteeIds */
            $inviteeIds = array_map('intval', (array) $request->input('sub_invitee_ids', []));
            $subReason = $request->string('sub_reason')->toString() ?: null;
            $subWarning = $this->reconcileSubRequestForUpdatedSchedule(
                $therapist,
                $updated,
                $inviteeIds,
                $request->boolean('request_sub'),
                $subReason,
            );
        }

        if ($request->expectsJson()) {
            $response = ['schedule' => $updated];
            if ($subWarning !== null) {
                $response['sub_warning'] = $subWarning;
            }

            return response()->json($response);
        }

        if ($subWarning !== null) {
            return redirect()
                ->route('therapist.schedule.edit', $updated->id)
                ->with('status', 'Schedule updated successfully.')
                ->with('warning', $subWarning);
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
            'subTherapist',
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

    /**
     * Bundled side-effect for schedule create: raise a sub request and (for
     * recurring schedules) propagate to occurrences. The primary action — saving
     * the schedule — has already succeeded; failures here become a non-fatal
     * warning so the user can retry from the edit page (CLAUDE.md side-effects
     * rule: never let a side-effect break the primary action's HTTP semantics).
     *
     * @param  array<int, int>  $inviteeIds
     */
    private function raiseSubRequestForNewSchedule(User $requester, Schedule $schedule, array $inviteeIds, ?string $reason): ?string
    {
        try {
            $result = $this->subRequestService->createForScheduleAndOccurrences($requester, $schedule, $inviteeIds, $reason);
            if ($result['skipped'] > 0) {
                return "Sub request created for {$result['created']} session(s); {$result['skipped']} occurrence(s) were skipped (already past the cutoff or otherwise ineligible).";
            }

            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('ScheduleController@store: failed to create sub request after schedule save', [
                'schedule_id' => $schedule->id,
                'exception' => $e,
            ]);

            return 'Schedule saved, but the sub request could not be created. Please try again from the schedule edit page.';
        }
    }

    /**
     * Side-effect for the make-up booking flow: link the booked schedule back
     * to the originating make-up request, stamp the therapist as the actor,
     * and flip the request's status to SCHEDULED. Used by both paths — an
     * in-place reschedule (update) of the missed session and the create-new
     * fallback when the original session was deleted.
     *
     * The schedule has already been saved by the time we get here, so any
     * failure must not propagate to the user.
     */
    private function linkMakeupRequestSchedule(User $therapist, int $makeupRequestId, int $scheduleId): void
    {
        try {
            $makeupRequest = ScheduleMakeupRequest::find($makeupRequestId);
            if ($makeupRequest === null) {
                return;
            }

            // Only the owning therapist may book, and only a request that is
            // still awaiting booking. Guards against a stale/forged
            // makeup_request_id reaching the schedule endpoints directly.
            if ((int) $makeupRequest->therapist_id !== (int) $therapist->id
                || ! $makeupRequest->isRequested()
                || $makeupRequest->makeup_schedule_id !== null) {
                return;
            }

            $schedule = Schedule::find($scheduleId);
            if ($schedule !== null) {
                $schedule->forceFill(['updated_by' => $therapist->id])->save();
            }

            $this->makeupRequestRepository->linkBookedSchedule($makeupRequest, $scheduleId);
        } catch (\Throwable $e) {
            Log::error('ScheduleController: failed to link make-up request to schedule', [
                'makeup_request_id' => $makeupRequestId,
                'schedule_id' => $scheduleId,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Bundled side-effect for schedule update: either sync invitees on the
     * existing open request, or raise a new one. Same swallow-and-warn contract
     * as raiseSubRequestForNewSchedule().
     *
     * @param  array<int, int>  $inviteeIds
     */
    private function reconcileSubRequestForUpdatedSchedule(User $requester, Schedule $schedule, array $inviteeIds, bool $requestSubFlag, ?string $reason): ?string
    {
        $subRequest = $schedule->activeSubRequest;

        if ($subRequest !== null && $subRequest->isOpen()) {
            try {
                $this->subRequestService->syncInvitees($requester, $subRequest, $inviteeIds);

                return null;
            } catch (\InvalidArgumentException $e) {
                return $e->getMessage();
            } catch (\Throwable $e) {
                Log::error('ScheduleController@update: failed to sync sub invitees', [
                    'schedule_id' => $schedule->id,
                    'exception' => $e,
                ]);

                return 'Schedule updated, but sub invitees could not be saved. Please try again from the schedule edit page.';
            }
        }

        if ($subRequest === null && $requestSubFlag) {
            return $this->raiseSubRequestForNewSchedule($requester, $schedule, $inviteeIds, $reason);
        }

        return null;
    }

    /**
     * Holiday-date list for the scheduling form's inline warning. Failure
     * here is non-fatal — the form still renders; the user simply loses
     * the holiday warning hint.
     *
     * @return array<int, string>
     */
    private function resolveUpcomingHolidayDates(int $schoolId): array
    {
        try {
            return $this->calendarService->listHolidayDateStringsForSchool(
                $schoolId,
                CarbonImmutable::today(),
                CarbonImmutable::today()->addYear(),
            );
        } catch (Throwable $e) {
            Log::error('ScheduleController: failed to load holiday dates for form', [
                'school_id' => $schoolId,
                'exception' => $e,
            ]);

            return [];
        }
    }
}
