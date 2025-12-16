<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\Therapist\Services\SessionLogService;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\SessionLogIndexDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionLog\SessionLogIndexRequest;
use App\Http\Requests\Therapist\StoreSessionLogRequest;
use App\Http\Requests\Therapist\UpdateSessionLogRequest;
use App\Models\Schedule;
use App\Models\SessionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SessionLogController extends Controller
{
    public function __construct(
        private readonly SessionLogService $sessionLogService,
        private readonly SessionLogIndexService $sessionLogIndexService,
    ) {}

    public function index(SessionLogIndexRequest $request): View
    {
        $therapist = $request->user();

        $dto = SessionLogIndexDTO::fromArray($request->validated());
        $viewData = $this->sessionLogIndexService->getTherapistIndex($therapist, $dto);

        return view('therapist.session-logs.index', $viewData);
    }

    public function create(Request $request, ?Schedule $schedule = null): View
    {
        $therapist = $request->user();

        // If schedule provided, validate access
        if ($schedule && $schedule->therapist_id !== $therapist->id) {
            abort(403, 'You do not have access to this schedule.');
        }

        // Get students and SSAs for dropdowns
        $students = $this->sessionLogService->getSessionLogs($therapist)
            ->pluck('student')
            ->unique('id')
            ->values();

        $ssas = collect();
        if ($schedule) {
            $ssas = $this->sessionLogService->getActiveSSAsForStudent($schedule->student_id);
        }

        return view('therapist.session-logs.create', [
            'schedule' => $schedule,
            'students' => $students,
            'ssas' => $ssas,
        ]);
    }

    public function store(StoreSessionLogRequest $request): RedirectResponse
    {
        $therapist = $request->user();
        $data = $request->validated();

        try {
            $dto = CreateSessionLogDTO::fromArray(array_merge($data, [
                'therapist_id' => $therapist->id,
            ]));

            // Check if schedule is provided
            $schedule = null;
            if (isset($data['schedule_id'])) {
                $schedule = Schedule::findOrFail($data['schedule_id']);
                $sessionLog = $this->sessionLogService->createFromSchedule($therapist, $schedule, $dto);
            } else {
                $sessionLog = $this->sessionLogService->createStandalone($therapist, $dto);
            }

            return redirect()
                ->route('therapist.session-logs.show', $sessionLog)
                ->with('success', 'Session log created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(SessionLog $sessionLog): View
    {
        $this->authorize('view', $sessionLog);

        $sessionLog->load(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule', 'therapistContract', 'schoolContract']);

        return view('therapist.session-logs.show', [
            'sessionLog' => $sessionLog,
        ]);
    }

    public function edit(SessionLog $sessionLog): View
    {
        $this->authorize('update', $sessionLog);

        $sessionLog->load(['student', 'ssa', 'service', 'school']);

        return view('therapist.session-logs.edit', [
            'sessionLog' => $sessionLog,
        ]);
    }

    public function update(UpdateSessionLogRequest $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('update', $sessionLog);

        $therapist = $request->user();
        $data = $request->validated();

        try {
            $dto = UpdateSessionLogDTO::fromArray($data);
            $this->sessionLogService->update($therapist, $sessionLog, $dto);

            return redirect()
                ->route('therapist.session-logs.show', $sessionLog)
                ->with('success', 'Session log updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function submit(Request $request, SessionLog $sessionLog): RedirectResponse
    {
        $therapist = $request->user();

        // Ownership guard: therapists may only submit their own logs
        if ((int) $sessionLog->therapist_id !== (int) $therapist->id) {
            abort(403, 'Therapist does not have access to this session log.');
        }

        // Finalization guard: finalized logs cannot be resubmitted
        if ($sessionLog->isFinalized()) {
            abort(403, 'Session log cannot be submitted in its current status.');
        }

        try {
            $this->sessionLogService->submit($therapist, $sessionLog);

            return redirect()
                ->route('therapist.session-logs.show', ['sessionLog' => $sessionLog->id])
                ->with('success', 'Session log submitted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, SessionLog $sessionLog): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $sessionLog);

        $therapist = $request->user();
        $reason = $request->input('cancellation_reason', 'Cancelled by therapist');

        try {
            $this->sessionLogService->cancel($therapist, $sessionLog, $reason);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session log cancelled successfully.']);
            }

            return redirect()
                ->route('therapist.session-logs.index')
                ->with('success', 'Session log cancelled successfully.');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
