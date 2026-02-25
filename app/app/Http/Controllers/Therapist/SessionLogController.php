<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\TherapistSessionLogRowTransformer;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Therapist\Services\SessionLogService;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionLog\SessionLogIndexRequest;
use App\Http\Requests\Therapist\StoreSessionLogRequest;
use App\Http\Requests\Therapist\TherapistSessionLogDataRequest;
use App\Http\Requests\Therapist\UpdateSessionLogRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Schedule;
use App\Models\SessionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class SessionLogController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'session_logs.session_date',
        4 => 'session_logs.therapist_billable_amount',
        5 => 'session_logs.status',
    ];

    public function __construct(
        private readonly SessionLogService $sessionLogService,
        private readonly SessionLogIndexService $sessionLogIndexService,
        private readonly SSAService $ssaService,
        private readonly StudentDocumentService $documentService,
    ) {}

    public function selectSSA(Request $request): View
    {
        $therapist = $request->user();

        $ssas = $this->ssaService
            ->getActiveSSAsForTherapist($therapist->id)
            ->loadMissing(['student', 'primaryService']);

        return view('therapist.session-logs.select-ssa', [
            'ssas' => $ssas,
        ]);
    }

    public function index(SessionLogIndexRequest $request): View
    {
        $therapist = $request->user();
        $filters = $request->validated();

        $ssas = $this->ssaService
            ->getActiveSSAsForTherapist($therapist->id)
            ->loadMissing(['student.studentProfile', 'services']);

        /** @var Collection<int, \App\Models\User> $students */
        $students = $ssas
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        /** @var Collection<int, \App\Models\Service> $services */
        $services = $ssas
            ->flatMap(fn ($ssa) => $ssa->services)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('therapist.session-logs.index', [
            'sessionLogs' => collect(),
            'columns' => [],
            'rows' => [],
            'statuses' => SessionLogStatus::cases(),
            'filters' => $filters,
            'students' => $students,
            'ssas' => $ssas,
            'services' => $services,
            'datatableUrl' => route('therapist.session-logs.data'),
        ]);
    }

    public function data(TherapistSessionLogDataRequest $request): JsonResponse
    {
        $therapist = $request->user();

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = [
            'student_id' => $request->input('filter_student_id'),
            'ssa_id' => $request->input('filter_ssa_id'),
            'service_id' => $request->input('filter_service_id'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $result = $this->sessionLogIndexService->listForDataTablesForTherapist($therapist, $filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (SessionLog $log): array => TherapistSessionLogRowTransformer::transform($log),
        );
    }

    public function create(Request $request, ?Schedule $schedule = null): View
    {
        $therapist = $request->user();

        // If schedule provided, validate access
        if ($schedule && $schedule->therapist_id !== $therapist->id) {
            abort(403, 'You do not have access to this schedule.');
        }

        // Get active SSAs assigned to the therapist
        $ssas = $this->ssaService
            ->getActiveSSAsForTherapist($therapist->id)
            ->loadMissing(['student', 'primaryService', 'services']);

        // When coming from the standalone flow, an SSA must be selected first.
        $selectedSsaId = (int) $request->query('ssa_id', 0);
        $selectedSsa = $selectedSsaId > 0 ? $ssas->firstWhere('id', $selectedSsaId) : null;

        // When coming from a schedule, default the SSA from the schedule if not explicitly selected.
        if (! $selectedSsa && $schedule && $schedule->ssa_id) {
            $selectedSsa = $ssas->firstWhere('id', $schedule->ssa_id);
        }

        // Derive unique students from active SSAs
        $students = $ssas
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();

        // Build SSA -> services mapping for front-end
        $ssaServiceMappings = $ssas->map(function ($ssa) {
            return [
                'ssa_id' => $ssa->id,
                'services' => $ssa->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                    ];
                })->values()->all(),
            ];
        })->values();

        $services = collect();
        if ($selectedSsa) {
            $services = $selectedSsa->services->map(function ($service) {
                return (object) [
                    'id' => $service->id,
                    'name' => $service->name,
                ];
            });
        }

        return view('therapist.session-logs.create', [
            'schedule' => $schedule,
            'students' => $students,
            'ssas' => $ssas,
            'services' => $services,
            'ssaServiceMappings' => $ssaServiceMappings,
            'selectedSsa' => $selectedSsa,
            'sessionOutcomes' => SessionOutcome::cases(),
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

        $sessionLog->load(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule', 'therapistContract', 'schoolContract', 'comments.author']);

        $documents = $this->documentService->listBySessionLog($sessionLog->id);

        return view('therapist.session-logs.show', [
            'sessionLog' => $sessionLog,
            'documents' => $documents,
        ]);
    }

    public function edit(SessionLog $sessionLog): View
    {
        $this->authorize('update', $sessionLog);

        $sessionLog->load([
            'student',
            'ssa',
            'ssa.primaryService',
            'ssa.services',
            'service',
            'school',
            'comments.author',
        ]);

        return view('therapist.session-logs.edit', [
            'sessionLog' => $sessionLog,
            'sessionOutcomes' => SessionOutcome::cases(),
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
        $this->authorize('submit', $sessionLog);

        $therapist = $request->user();

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
