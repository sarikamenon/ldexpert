<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\Therapist\Services\ScheduleService;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ServiceStatus;
use App\Enums\SSAStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\ScheduleFilterRequest;
use App\Http\Requests\Therapist\StoreScheduleRequest;
use App\Http\Requests\Therapist\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function calendar(ScheduleFilterRequest $request): View
    {
        $therapist = $request->user();
        $filters = ScheduleFilterDTO::fromRequest($request->validated());

        $selectedDate = $filters->date
            ? CarbonImmutable::parse($filters->date)
            : CarbonImmutable::today();

        $schedules = $this->scheduleService->getSchedules($therapist, $filters);
        $pendingCount = $this->scheduleService->getPendingCount($therapist);
        $schools = $this->scheduleService->getSchools($therapist);
        $students = $this->scheduleService->getStudents($therapist);

        // Get active SSAs for the therapist
        $activeSSAs = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['student', 'primaryService'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Format schedules for the view (matching the format expected by schedule-card component)
        $formattedSchedules = $schedules->map(function ($schedule) {
            $studentProfile = $schedule->student?->studentProfile;
            $scheduleDate = $schedule->schedule_date;
            $isPast = $scheduleDate !== null && $scheduleDate->lt(now()->startOfDay());
            $isBilled = $schedule->billing_status === BillingStatus::BILLED;

            return [
                'id' => $schedule->id,
                'schedule_date' => $scheduleDate?->format('Y-m-d'),
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'school' => $schedule->school?->display_name,
                'student' => $schedule->student?->name,
                'student_url' => $schedule->student?->id
                    ? route('therapist.students.show', $schedule->student->id)
                    : null,
                'service' => $schedule->service?->name,
                'status' => $schedule->status?->value,
                'billing_status' => $schedule->billing_status?->value,
                'is_group' => $schedule->is_group,
                'is_past' => $isPast,
                'is_billed' => $isBilled,
                'notes' => $schedule->notes,
                'location_details' => $schedule->location_details,
                'student_name' => $schedule->student?->name,
                'student_password' => $studentProfile?->id_number ?? '-',
                'parent_name' => $studentProfile?->parent_guardian_name ?? '-',
                'parent_email' => $studentProfile?->parent_guardian_email ?? '-',
                'parent_phone' => $studentProfile?->parent_guardian_phone ?? '-',
                'edit_url' => route('therapist.schedule.edit', $schedule->id),
            ];
        });

        return view('therapist.schedule.calendar', [
            'selectedDate' => $selectedDate,
            'selectedDateFormatted' => $selectedDate->format('Y-m-d'),
            'schedules' => $formattedSchedules,
            'pendingCount' => $pendingCount,
            'schools' => $schools,
            'students' => $students,
            'selectedSchoolId' => $filters->schoolId,
            'selectedStudentId' => $filters->studentId,
            'activeSSAs' => $activeSSAs,
        ]);
    }

    public function create(Request $request): View
    {
        $therapist = $request->user();

        $selectedDate = $request->query('date')
            ? CarbonImmutable::parse($request->query('date'))
            : CarbonImmutable::today();

        $ssaId = $request->query('ssa_id');
        $ssa = null;
        $student = null;
        $service = null;

        // If ssa_id is provided, validate and load the SSA
        if ($ssaId) {
            $ssa = ServiceSupportAgreement::query()
                ->where('id', $ssaId)
                ->where('assigned_therapist_id', $therapist->id)
                ->where('status', SSAStatus::ACTIVE)
                ->with(['student', 'student.studentProfile.school', 'primaryService', 'services'])
                ->firstOrFail();

            $student = $ssa->student;
            $service = $ssa->primaryService;
        }

        // For first iteration: only single student, single schedule
        // Get students and services based on active SSAs only
        $activeSSAs = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->where('status', SSAStatus::ACTIVE)
            ->with(['student', 'primaryService'])
            ->get();

        // Build student list from active SSAs
        $students = $activeSSAs->map(function ($ssa) {
            return (object) [
                'user_id' => $ssa->student_id,
                'first_name' => $ssa->student->name ?? '',
                'last_name' => '',
            ];
        })->unique('user_id')->values();

        // If SSA is provided, use its services; otherwise build from all active SSAs
        if ($ssa) {
            // Get all services from this SSA (primary + additional)
            $ssaServices = $ssa->services()->where('status', ServiceStatus::ACTIVE)->get();
            $serviceOptions = $ssaServices->map(function ($service) use ($ssa) {
                return [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'is_primary' => (bool) $service->pivot?->is_primary,
                ];
            })->values();

            $studentServiceMappings = collect([
                [
                    'student_id' => $ssa->student_id,
                    'services' => $serviceOptions->toArray(),
                ],
            ]);
        } else {
            // Build service mappings from active SSAs
            $studentServiceMappings = $activeSSAs->map(function ($ssa) {
                $services = $ssa->services()->where('status', ServiceStatus::ACTIVE)->get();
                return [
                    'student_id' => $ssa->student_id,
                    'services' => $services->map(function ($service) {
                        return [
                            'service_id' => $service->id,
                            'service_name' => $service->name,
                            'is_primary' => (bool) $service->pivot?->is_primary,
                        ];
                    })->toArray(),
                ];
            })->toArray();

            $serviceOptions = collect($studentServiceMappings)
                ->flatMap(fn(array $entry) => $entry['services'])
                ->unique('service_id')
                ->values();
        }

        return view('therapist.schedule.create', [
            'selectedDate' => $selectedDate,
            'students' => $students,
            'schools' => $this->scheduleService->getSchools($therapist),
            'studentServiceMappings' => is_array($studentServiceMappings) ? collect($studentServiceMappings) : $studentServiceMappings,
            'serviceOptions' => $serviceOptions,
            'ssa' => $ssa,
            'preselectedStudent' => $student,
            'preselectedService' => $service,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $therapist = $request->user();
        $schedule = Schedule::query()
            ->where('id', $id)
            ->where('therapist_id', $therapist->id)
            ->with(['student', 'student.studentProfile', 'service', 'ssa', 'school'])
            ->firstOrFail();

        $this->authorize('update', $schedule);

        return view('therapist.schedule.edit', [
            'schedule' => $schedule,
        ]);
    }

    public function getSchedules(ScheduleFilterRequest $request): JsonResponse
    {
        $therapist = $request->user();
        $filters = ScheduleFilterDTO::fromRequest($request->validated());

        $schedules = $this->scheduleService->getSchedules($therapist, $filters);

        return response()->json([
            'schedules' => $schedules->map(function ($schedule) {
                $scheduleDate = $schedule->schedule_date;
                $isPast = $scheduleDate !== null && $scheduleDate->lt(now()->startOfDay());
                $isBilled = $schedule->billing_status === BillingStatus::BILLED;

                return [
                    'id' => $schedule->id,
                    'schedule_date' => $scheduleDate?->format('Y-m-d'),
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'school' => $schedule->school?->display_name,
                    'student' => $schedule->student?->name,
                    'service' => $schedule->service?->name,
                    'status' => $schedule->status?->value,
                    'billing_status' => $schedule->billing_status?->value,
                    'is_group' => $schedule->is_group,
                    'is_past' => $isPast,
                    'is_billed' => $isBilled,
                    'notes' => $schedule->notes,
                    'location_details' => $schedule->location_details,
                    'student_name' => $schedule->student?->name,
                    'student_password' => $schedule->student?->studentProfile?->id_number ?? '-',
                    'parent_name' => $schedule->student?->studentProfile?->parent_guardian_name ?? '-',
                    'parent_email' => $schedule->student?->studentProfile?->parent_guardian_email ?? '-',
                    'parent_phone' => $schedule->student?->studentProfile?->parent_guardian_phone ?? '-',
                    'edit_url' => route('therapist.schedule.edit', $schedule->id),
                ];
            })->toArray(),
        ]);
    }

    public function pending(ScheduleFilterRequest $request): View
    {
        $therapist = $request->user();
        $pendingCount = $this->scheduleService->getPendingCount($therapist);

        $pendingSchedules = Schedule::query()
            ->forTherapist($therapist)
            ->whereDate('schedule_date', '<', now()->toDateString())
            ->where('billing_status', BillingStatus::PENDING->value)
            ->whereIn('status', [ScheduleStatus::SCHEDULED->value, ScheduleStatus::COMPLETED->value])
            ->with(['student', 'service', 'ssa', 'school'])
            ->orderBy('schedule_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return view('therapist.schedule.pending', [
            'pendingSchedules' => $pendingSchedules,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function store(StoreScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        $therapist = $request->user();

        $data = $request->validated();
        $data['therapist_id'] = $therapist->id;
        $data['is_group'] = count($data['student_ids'] ?? []) > 1;

        $dto = CreateScheduleDTO::fromArray($data);

        $schedule = $this->scheduleService->createSchedule($therapist, $dto)
            ->load(['student', 'service', 'ssa', 'school']);

        if ($request->expectsJson()) {
            return response()->json([
                'schedule' => $schedule,
            ], 201);
        }

        return redirect()
            ->route('therapist.schedule.calendar', [
                'date' => $schedule->schedule_date?->format('Y-m-d') ?? $request->input('schedule_date'),
            ])
            ->with('status', 'Schedule created successfully.');
    }

    public function update(UpdateScheduleRequest $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('update', $schedule);

        $therapist = $request->user();
        $dto = UpdateScheduleDTO::fromArray($request->validated());

        $updated = $this->scheduleService->updateSchedule($therapist, $id, $dto)
            ->load(['student', 'service', 'ssa', 'school']);

        if ($request->expectsJson()) {
            return response()->json([
                'schedule' => $updated,
            ]);
        }

        return redirect()
            ->route('therapist.schedule.calendar', [
                'date' => $updated->schedule_date?->format('Y-m-d') ?? $request->input('schedule_date'),
            ])
            ->with('status', 'Schedule updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('delete', $schedule);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $this->scheduleService->deleteSchedule($therapist, $id);

        return response()->json([
            'success' => true,
        ]);
    }

    public function removeStudent(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('delete', $schedule);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $this->scheduleService->removeStudentFromOccurrence($therapist, $id);

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateBillingStatus(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('updateBillingStatus', $schedule);

        $billingStatuses = array_map(
            static fn(BillingStatus $status): string => $status->value,
            BillingStatus::cases()
        );

        $validated = $request->validate([
            'billing_status' => ['required', 'string', Rule::in($billingStatuses)],
        ]);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

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
            static fn(BillingStatus $status): string => $status->value,
            BillingStatus::cases()
        );

        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['required', 'integer'],
            'billing_status' => ['required', 'string', Rule::in($billingStatuses)],
        ]);

        /** @var \App\Models\User $therapist */
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

    public function show(Request $request, int $id): JsonResponse
    {
        $therapist = $request->user();
        $schedule = Schedule::query()
            ->where('id', $id)
            ->where('therapist_id', $therapist->id)
            ->with([
                'student',
                'student.studentProfile',
                'student.studentProfile.school',
                'service',
                'ssa',
                'ssa.primaryService',
                'school',
            ])
            ->firstOrFail();

        $this->authorize('view', $schedule);

        $studentProfile = $schedule->student?->studentProfile;
        $ssa = $schedule->ssa;
        $durationMinutes = $schedule->durationMinutes();

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
                'schedule_date_formatted' => $schedule->schedule_date?->format('M d, Y'),
                'start_time' => $schedule->start_time?->format('H:i'),
                'start_time_formatted' => $schedule->start_time?->format('g:i A'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'end_time_formatted' => $schedule->end_time?->format('g:i A'),
                'duration_minutes' => $durationMinutes,
                'duration_formatted' => $this->formatDuration($durationMinutes),
                'status' => $schedule->status?->value,
                'billing_status' => $schedule->billing_status?->value,
                'notes' => $schedule->notes,
                'location_details' => $schedule->location_details,
                'service' => [
                    'id' => $schedule->service?->id,
                    'name' => $schedule->service?->name,
                ],
                'ssa' => $ssa ? [
                    'id' => $ssa->id,
                    'start_date' => $ssa->start_date?->format('Y-m-d'),
                    'start_date_formatted' => $ssa->start_date?->format('M d, Y'),
                    'end_date' => $ssa->end_date?->format('Y-m-d'),
                    'end_date_formatted' => $ssa->end_date?->format('M d, Y'),
                    'minutes_per_session' => $ssa->minutes_per_session,
                    'frequency' => $ssa->frequency?->value,
                    'sessions_per_frequency' => $ssa->sessions_per_frequency,
                    'status' => $ssa->status?->value,
                    'tho_minutes' => $ssa->tho_minutes ?? 0,
                    'served_minutes' => $ssa->served_minutes ?? 0,
                    'service' => [
                        'id' => $ssa->primaryService?->id,
                        'name' => $ssa->primaryService?->name,
                    ],
                ] : null,
                'student' => [
                    'id' => $schedule->student?->id,
                    'name' => $schedule->student?->name,
                    'email' => $schedule->student?->email,
                    'id_number' => $studentProfile?->id_number ?? '-',
                    'timezone' => $studentProfile?->timezone ?? '-',
                ],
                'school' => [
                    'id' => $schedule->school?->id,
                    'name' => $schedule->school?->display_name ?? $schedule->school?->name,
                ],
                'parent' => [
                    'name' => $studentProfile?->parent_guardian_name ?? '-',
                    'email' => $studentProfile?->parent_guardian_email ?? '-',
                    'phone' => $studentProfile?->parent_guardian_phone ?? '-',
                ],
            ],
        ]);
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }
}
