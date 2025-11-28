<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\Therapist\Services\ScheduleService;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\ScheduleFilterRequest;
use App\Http\Requests\Therapist\StoreScheduleRequest;
use App\Http\Requests\Therapist\UpdateScheduleRequest;
use App\Models\Schedule;
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

        return view('therapist.schedule.calendar', [
            'selectedDate' => $selectedDate,
            'selectedDateFormatted' => $selectedDate->format('Y-m-d'),
            'schedules' => $schedules,
            'pendingCount' => $pendingCount,
            'schools' => $schools,
            'students' => $students,
            'selectedSchoolId' => $filters->schoolId,
            'selectedStudentId' => $filters->studentId,
        ]);
    }

    public function create(Request $request): View
    {
        $therapist = $request->user();

        $selectedDate = $request->query('date')
            ? CarbonImmutable::parse($request->query('date'))
            : CarbonImmutable::today();

        $students = $this->scheduleService->getStudents($therapist);
        $schools = $this->scheduleService->getSchools($therapist);
        $studentServiceMappings = $this->scheduleService->getStudentServiceMappings($therapist);
        $serviceOptions = $studentServiceMappings
            ->flatMap(fn(array $entry) => $entry['services'])
            ->unique('service_id')
            ->values();

        return view('therapist.schedule.create', [
            'selectedDate' => $selectedDate,
            'students' => $students,
            'schools' => $schools,
            'studentServiceMappings' => $studentServiceMappings,
            'serviceOptions' => $serviceOptions,
        ]);
    }

    public function getSchedules(ScheduleFilterRequest $request): JsonResponse
    {
        $therapist = $request->user();
        $filters = ScheduleFilterDTO::fromRequest($request->validated());

        $schedules = $this->scheduleService->getSchedules($therapist, $filters);

        return response()->json([
            'schedules' => $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'school' => $schedule->school?->display_name,
                    'student' => $schedule->student?->name,
                    'service' => $schedule->service?->name,
                    'status' => $schedule->status?->value,
                    'billing_status' => $schedule->billing_status?->value,
                    'is_group' => $schedule->is_group,
                    'notes' => $schedule->notes,
                ];
            })->toArray(),
        ]);
    }

    public function pending(ScheduleFilterRequest $request): View
    {
        $therapist = $request->user();
        $pendingSchedules = $this->scheduleService->getSchedules(
            $therapist,
            ScheduleFilterDTO::fromRequest(['date' => null])
        )->filter(fn($schedule) => $schedule->status?->value === 'scheduled');

        return view('therapist.schedule.pending', [
            'pendingSchedules' => $pendingSchedules,
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

    public function update(UpdateScheduleRequest $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('update', $schedule);

        $therapist = $request->user();
        $dto = UpdateScheduleDTO::fromArray($request->validated());

        $updated = $this->scheduleService->updateSchedule($therapist, $id, $dto)
            ->load(['student', 'service', 'ssa', 'school']);

        return response()->json([
            'schedule' => $updated,
        ]);
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
}
