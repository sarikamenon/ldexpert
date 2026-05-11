<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\TherapistSessionLogRowTransformer;
use App\Domain\Billing\Services\BillingEntryWindowService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAGoalService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogService;
use App\Domain\Time\UserTimezoneService;
use App\DTOs\CreateSessionLogDTO;
use App\DTOs\UpdateSessionLogDTO;
use App\Enums\SessionLogCommentType;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionLog\SessionLogIndexRequest;
use App\Http\Requests\Therapist\AddTherapistCommentRequest;
use App\Http\Requests\Therapist\EntryWindowRequest;
use App\Http\Requests\Therapist\StoreSessionLogRequest;
use App\Http\Requests\Therapist\TherapistSessionLogDataRequest;
use App\Http\Requests\Therapist\UpdateSessionLogRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\SessionLogComment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Exceeds 300-line cap. Follow-up tracked in GitHub issue #197: split state-transition
// actions (submit, cancel) into single-action controllers and extract buildSsaContext()
// into a dedicated service.
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
        private readonly BillingEntryWindowService $billingEntryWindowService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly UserTimezoneService $timezoneService,
        private readonly SSAGoalService $goalService,
        private readonly SessionLogRepositoryInterface $sessionLogRepository,
    ) {}

    public function selectSSA(Request $request): View
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $ssas = $this->ssaService
            ->getActiveSSAsForTherapist($therapist->id)
            ->loadMissing(['student', 'primaryService']);

        return view('therapist.session-logs.select-ssa', [
            'ssas' => $ssas,
        ]);
    }

    public function entryWindow(EntryWindowRequest $request): JsonResponse
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $tz = $this->timezoneService->resolveTimezone($therapist);
        $sessionDate = Carbon::parse((string) $request->validated('session_date'), $tz);
        $result = $this->billingEntryWindowService->checkWindow($sessionDate, null, $tz);

        return response()->json(array_merge($result->toArray(), [
            'cutoff_display' => Carbon::parse($result->cutoff, $tz)->format('l, M j, Y'),
        ]));
    }

    public function index(SessionLogIndexRequest $request): View
    {
        /** @var \App\Models\User $therapist */
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
        /** @var \App\Models\User $therapist */
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
        /** @var \App\Models\User $therapist */
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

        // Build SSA -> services mapping for front-end.
        // For standalone session logs, show indirect services from the school+therapist contracts.
        $isStandalone = $schedule === null;

        $therapistProfileId = $therapist->therapistProfile?->id;
        $ssaServiceMappings = $ssas->map(function ($ssa) use ($isStandalone, $therapistProfileId) {
            if ($isStandalone) {
                $schoolId = $ssa->student?->studentProfile?->school_id;
                $availableServices = ($schoolId && $therapistProfileId)
                    ? $this->serviceCatalogService->listCommonIndirectServices($therapistProfileId, $schoolId)
                    : collect();
            } else {
                $availableServices = $ssa->services;
            }

            return [
                'ssa_id' => $ssa->id,
                'primary_service_id' => $isStandalone ? null : $ssa->primary_service_id,
                'services' => $availableServices->map(static fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ])->values()->all(),
            ];
        })->values();

        $services = collect();
        if ($selectedSsa) {
            if ($isStandalone) {
                $schoolId = $selectedSsa->student?->studentProfile?->school_id;
                $availableServices = ($schoolId && $therapistProfileId)
                    ? $this->serviceCatalogService->listCommonIndirectServices($therapistProfileId, $schoolId)
                    : collect();
            } else {
                $availableServices = $selectedSsa->services;
            }

            $services = $availableServices->map(static fn ($s) => (object) [
                'id' => $s->id,
                'name' => $s->name,
            ]);
        }

        $scheduleLocalDate = null;
        $scheduleLocalStartTime = null;
        $scheduleLocalEndTime = null;
        if ($schedule !== null) {
            $tz = $this->timezoneService->resolveTimezone($therapist);
            $localStart = $schedule->localStart($tz);
            $localEnd = $schedule->localEnd($tz);
            $scheduleLocalDate = $localStart->format('Y-m-d');
            $scheduleLocalStartTime = $localStart->format('H:i');
            $scheduleLocalEndTime = $localEnd->format('H:i');
        }

        return view('therapist.session-logs.create', [
            'schedule' => $schedule,
            'students' => $students,
            'ssas' => $ssas,
            'services' => $services,
            'ssaServiceMappings' => $ssaServiceMappings,
            'selectedSsa' => $selectedSsa,
            'sessionOutcomes' => SessionOutcome::cases(),
            'scheduleLocalDate' => $scheduleLocalDate,
            'scheduleLocalStartTime' => $scheduleLocalStartTime,
            'scheduleLocalEndTime' => $scheduleLocalEndTime,
            'ssaContext' => $this->buildSsaContext($selectedSsa),
        ]);
    }

    public function store(StoreSessionLogRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $data = $request->validated();

        try {
            $dto = CreateSessionLogDTO::fromArray(array_merge($data, [
                'therapist_id' => $therapist->id,
            ]));

            // Check if schedule is provided
            $schedule = null;
            if (isset($data['schedule_id'])) {
                /** @var Schedule $schedule */
                $schedule = Schedule::findOrFail($data['schedule_id']);
                $sessionLog = $this->sessionLogService->createFromSchedule($therapist, $schedule, $dto);
            } else {
                $sessionLog = $this->sessionLogService->createStandalone($therapist, $dto);
            }

            return redirect()
                ->route('therapist.session-logs.show', $sessionLog)
                ->with('success', 'Session log created successfully.');
        } catch (ValidationException $e) {
            throw $e;
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

        $sessionLog->load(['student', 'student.studentProfile', 'ssa', 'service', 'school', 'schedule', 'therapist', 'therapist.therapistProfile', 'therapistContract', 'schoolContract', 'comments.author']);

        $tz = $sessionLog->displayTimezone();
        $sessionLog->session_date_formatted = $sessionLog->localDate($tz)->format('M d, Y');
        $sessionLog->start_time_formatted = $sessionLog->localStart($tz)->format('g:i A');
        $sessionLog->end_time_formatted = $sessionLog->localEnd($tz)->format('g:i A');

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
            'therapist',
            'therapist.therapistProfile',
            'comments.author',
        ]);

        $tz = $sessionLog->displayTimezone();
        $localStart = $sessionLog->localStart($tz);
        $localEnd = $sessionLog->localEnd($tz);
        $sessionLog->session_date_input = $localStart->format('Y-m-d');
        $sessionLog->start_time_input = $localStart->format('H:i');
        $sessionLog->end_time_input = $localEnd->format('H:i');

        return view('therapist.session-logs.edit', [
            'sessionLog' => $sessionLog,
            'sessionOutcomes' => SessionOutcome::cases(),
            'ssaContext' => $this->buildSsaContext($sessionLog->ssa, $sessionLog->id),
        ]);
    }

    public function update(UpdateSessionLogRequest $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('update', $sessionLog);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $data = $request->validated();

        try {
            $dto = UpdateSessionLogDTO::fromArray($data);
            $this->sessionLogService->update($therapist, $sessionLog, $dto);

            return redirect()
                ->route('therapist.session-logs.show', $sessionLog)
                ->with('success', 'Session log updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function addComment(AddTherapistCommentRequest $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('view', $sessionLog);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        SessionLogComment::create([
            'session_log_id' => $sessionLog->id,
            'author_id' => $therapist->id,
            'comment' => $request->validated('comment'),
            'type' => SessionLogCommentType::THERAPIST_REPLY,
        ]);

        if ($request->boolean('submit_after_comment') && $sessionLog->status?->canSubmit()) {
            try {
                $this->sessionLogService->submit($therapist, $sessionLog);

                return redirect()
                    ->route('therapist.session-logs.show', $sessionLog)
                    ->with('success', 'Comment added and session log submitted successfully.');
            } catch (\Exception $e) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $e->getMessage()]);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Comment added successfully.');
    }

    public function submit(Request $request, SessionLog $sessionLog): RedirectResponse
    {
        $this->authorize('submit', $sessionLog);

        /** @var \App\Models\User $therapist */
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

        /** @var \App\Models\User $therapist */
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

    /**
     * Build the read-only context shown above the session log form.
     *
     * Returns either the SSA's active goals (first session log on the SSA)
     * or the most recent submitted/approved log's notes (subsequent logs).
     *
     * @return array{mode: string, goals?: \Illuminate\Database\Eloquent\Collection<int, \App\Models\SSAGoal>, previousLog?: SessionLog|null}|null
     */
    private function buildSsaContext(?ServiceSupportAgreement $ssa, ?int $excludeSessionLogId = null): ?array
    {
        if ($ssa === null) {
            return null;
        }

        $hasPrior = $this->sessionLogRepository->existsForSsaWithStatuses(
            $ssa->id,
            [SessionLogStatus::SUBMITTED, SessionLogStatus::APPROVED],
            $excludeSessionLogId,
        );

        if (! $hasPrior) {
            return [
                'mode' => 'goals',
                'goals' => $this->goalService->listActiveForSsa($ssa->id),
            ];
        }

        $previousLog = $this->sessionLogRepository->mostRecentSubmittedOrApprovedForSsa($ssa->id, $excludeSessionLogId);

        if ($previousLog !== null) {
            $tz = $previousLog->displayTimezone();
            $previousLog->session_date_formatted = $previousLog->localDate($tz)->format('M d, Y');
            $previousLog->start_time_formatted = $previousLog->localStart($tz)->format('g:i A');
            $previousLog->end_time_formatted = $previousLog->localEnd($tz)->format('g:i A');
        }

        return [
            'mode' => 'previous_notes',
            'previousLog' => $previousLog,
        ];
    }
}
