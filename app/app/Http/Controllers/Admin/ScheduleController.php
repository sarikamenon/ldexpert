<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Schedule\Presenters\ScheduleFormPresenter;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\User\Services\UserService;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Exceptions\CannotDeleteBilledScheduleException;
use App\Exceptions\CannotDeleteScheduleWithMakeupException;
use App\Exceptions\ScheduleOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Schedule\StoreScheduleRequest;
use App\Http\Requests\Admin\Schedule\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class ScheduleController extends Controller
{
    /** Relations needed to render the edit form and resolve its view-model. */
    private const EDIT_RELATIONS = [
        'student',
        'student.studentProfile',
        'student.studentProfile.school',
        'service',
        'ssa',
        'ssa.primaryService',
        'school',
        'therapist.therapistProfile',
    ];

    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSAService $ssaService,
        private readonly UserService $userService,
        private readonly ScheduleFormPresenter $formPresenter,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        $therapistId = $request->query('therapist_id') !== null ? (int) $request->query('therapist_id') : null;
        $ssaId = $request->query('ssa_id') !== null ? (int) $request->query('ssa_id') : null;
        $selectedDate = $request->query('date')
            ? CarbonImmutable::parse($request->query('date'))
            : CarbonImmutable::today();

        // Therapist + SSA are chosen via the calendar's "Add New Schedule" modal.
        if (! $therapistId || ! $ssaId) {
            return redirect()->route('admin.schedule-calendar.index')
                ->with('error', 'Please use the "Add New Schedule" button and select a therapist and SSA.');
        }

        $therapist = $this->userService->findById($therapistId);
        if (! $therapist || ! $therapist->isTherapist()) {
            return redirect()->route('admin.schedule-calendar.index')
                ->with('error', 'Invalid therapist selected.');
        }

        $ssa = $this->ssaService->findSSAForSchedule($ssaId, $therapistId);
        if (! $ssa) {
            return redirect()->route('admin.schedule-calendar.index')
                ->with('error', 'The selected SSA does not belong to this therapist or is not active.');
        }

        // The form renders the student as a required hidden field; guarantee it is present
        // here rather than null-checking deep in the view.
        if (! $ssa->student) {
            return redirect()->route('admin.schedule-calendar.index')
                ->with('error', 'The selected SSA has no associated student.');
        }

        return view('admin.schedule.create', [
            'therapistId' => $therapistId,
            'therapistName' => $therapist->name,
            'ssa' => $ssa,
            ...$this->formPresenter->forCreate($therapist, $ssa, $selectedDate),
        ]);
    }

    public function therapistSsas(Request $request): JsonResponse
    {
        $this->authorize('create', Schedule::class);

        $therapistId = (int) $request->query('therapist_id', 0);
        $ssas = $this->ssaService->getActiveSSAsForTherapist($therapistId);

        return response()->json(
            $ssas->map(static fn ($ssa) => [
                'id' => $ssa->id,
                'student_name' => $ssa->student->name ?? 'Unknown',
                'label' => "SSA #{$ssa->id} – ".($ssa->student->name ?? 'Unknown'),
            ])->values()
        );
    }

    public function store(StoreScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        $data = $request->validated();

        $therapist = $this->userService->findById((int) $data['therapist_id']);
        if (! $therapist) {
            abort(404);
        }

        $data['is_group'] = count($data['student_ids'] ?? []) > 1;

        $dto = CreateScheduleDTO::fromArray($data);

        try {
            $this->scheduleService->createSchedule($therapist, $dto, $request->user()?->id);
        } catch (ScheduleOverlapException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Schedule conflict detected.',
                    'errors' => ['start_time' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['service_id' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['service_id' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            Log::error('Admin\ScheduleController@store: unexpected error', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->with('error', 'An unexpected error occurred.')->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true], 201);
        }

        return redirect()
            ->route('admin.schedule-calendar.index')
            ->with('status', 'Schedule created successfully.');
    }

    public function edit(int $id): View
    {
        $schedule = $this->scheduleService->findById($id, self::EDIT_RELATIONS);
        if (! $schedule) {
            abort(404);
        }

        $this->authorize('update', $schedule);

        return view('admin.schedule.edit', [
            'schedule' => $schedule,
            ...$this->formPresenter->forEdit($schedule),
        ]);
    }

    public function update(UpdateScheduleRequest $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = $this->scheduleService->findById($id);
        if (! $schedule) {
            abort(404);
        }

        $this->authorize('update', $schedule);

        /** @var User $therapist */
        $therapist = $schedule->therapist;

        $dto = UpdateScheduleDTO::fromArray($request->validated());

        try {
            $this->scheduleService->updateSchedule($therapist, $id, $dto, $request->user()?->id);
        } catch (ScheduleOverlapException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Schedule conflict detected.',
                    'errors' => ['start_time' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            Log::error('Admin\ScheduleController@update: unexpected error', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->with('error', 'An unexpected error occurred.')->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('admin.schedule-calendar.index')
            ->with('status', 'Schedule updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $schedule = $this->scheduleService->findById($id);
        if (! $schedule) {
            abort(404);
        }

        $this->authorize('delete', $schedule);

        /** @var User $therapist */
        $therapist = $schedule->therapist;

        try {
            $this->scheduleService->deleteSchedule($therapist, $id);
        } catch (CannotDeleteBilledScheduleException|CannotDeleteScheduleWithMakeupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Admin\ScheduleController@destroy: unexpected error', ['exception' => $e]);

            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function destroyFutureRecurring(int $id): JsonResponse
    {
        $schedule = $this->scheduleService->findById($id);
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

        /** @var User $therapist */
        $therapist = $schedule->therapist;

        try {
            $deletedCount = $this->scheduleService->deleteFutureRecurringSchedules($therapist, $id);
        } catch (CannotDeleteBilledScheduleException|CannotDeleteScheduleWithMakeupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Admin\ScheduleController@destroyFutureRecurring: unexpected error', ['exception' => $e]);

            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
        ]);
    }
}
