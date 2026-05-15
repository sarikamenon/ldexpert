<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\SubRequestRowTransformer;
use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\AcceptSubRequestRequest;
use App\Http\Requests\Therapist\StoreSubRequestRequest;
use App\Http\Requests\Therapist\UpdateSubRequestInviteesRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class SubRequestController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'schedules.schedule_date',
    ];

    public function __construct(
        private readonly ScheduleSubRequestService $subRequestService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ScheduleSubRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $openCount = $this->subRequestService->countOpenForTherapist($therapist);

        return view('therapist.sub-requests.index', [
            'openCount' => $openCount,
            'datatableUrl' => route('therapist.sub-requests.data'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleSubRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $all = $this->subRequestService->listOpenForTherapist($therapist);
        $total = $all->count();
        $page = $all->slice($params->start, $params->length)->values();

        return $this->dataTablesResponse(
            $params,
            $total,
            $total,
            $page,
            static fn (ScheduleSubRequest $row): array => SubRequestRowTransformer::transform($row),
        );
    }

    public function store(StoreSubRequestRequest $request, Schedule $schedule): JsonResponse|RedirectResponse
    {
        $this->authorize('createSubRequest', $schedule);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $validated = $request->validated();
        $reason = isset($validated['reason']) && is_string($validated['reason']) ? $validated['reason'] : null;
        /** @var array<int, int> $inviteeIds */
        $inviteeIds = array_map('intval', (array) ($validated['invitee_ids'] ?? []));

        try {
            $this->subRequestService->create($therapist, $schedule, $inviteeIds, $reason);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['sub_request' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            Log::error('SubRequestController::store failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['sub_request' => 'An unexpected error occurred.'])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Sub request created successfully.'], 201);
        }

        return redirect()
            ->route('therapist.schedule-calendar.index')
            ->with('status', 'Sub request created successfully.');
    }

    public function accept(AcceptSubRequestRequest $request, ScheduleSubRequest $subRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('accept', $subRequest);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        try {
            $this->subRequestService->accept($therapist, $subRequest);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['sub_request' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('SubRequestController::accept failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['sub_request' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You have accepted the sub request.']);
        }

        return redirect()
            ->route('therapist.sub-requests.index')
            ->with('status', 'You have accepted the sub request.');
    }

    public function decline(Request $request, ScheduleSubRequest $subRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('decline', $subRequest);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        try {
            $this->subRequestService->decline($therapist, $subRequest);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['sub_request' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('SubRequestController::decline failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['sub_request' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You have declined the sub request.']);
        }

        return redirect()
            ->route('therapist.sub-requests.index')
            ->with('status', 'You have declined the sub request.');
    }

    public function updateInvitees(UpdateSubRequestInviteesRequest $request, ScheduleSubRequest $subRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('manageInvitees', $subRequest);

        /** @var \App\Models\User $actor */
        $actor = $request->user();
        /** @var array<int, int> $inviteeIds */
        $inviteeIds = array_map('intval', (array) ($request->validated()['invitee_ids'] ?? []));

        try {
            $this->subRequestService->syncInvitees($actor, $subRequest, $inviteeIds);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['invitee_ids' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('SubRequestController::updateInvitees failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['invitee_ids' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Invitees updated.']);
        }

        return back()->with('status', 'Invitees updated.');
    }

    /**
     * Return eligible subs for a picker.
     * - Without {subRequest}: create-time, takes ?service_id=&date= query params.
     * - With {subRequest}: edit-time, reads schedule from the request row; annotates invitee status.
     */
    public function eligibleSubs(Request $request, ?ScheduleSubRequest $subRequest = null): JsonResponse
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        if ($subRequest !== null) {
            // Edit-time: schedule already exists — derive from sub-request row.
            $schedule = $subRequest->schedule;
            if ($schedule === null) {
                return response()->json(['message' => 'Schedule not found.'], 404);
            }

            $eligibles = $this->subRequestService->listEligibleSubsFor($schedule);
        } elseif ($request->filled('schedule_id')) {
            // Edit-no-request: schedule exists but no sub-request yet — load via schedule_id.
            $schedule = Schedule::find((int) $request->query('schedule_id'));
            if ($schedule === null) {
                return response()->json(['message' => 'Schedule not found.'], 404);
            }

            $eligibles = $this->subRequestService->listEligibleSubsFor($schedule);
        } else {
            // Create-time: schedule not yet saved — use service_id + date directly.
            $serviceId = (int) $request->query('service_id', 0);
            $date = (string) $request->query('date', '');

            if ($serviceId === 0 || $date === '') {
                return response()->json(['message' => 'service_id and date are required.'], 422);
            }

            $eligibles = $this->subRequestService->listEligibleSubsForCreate($therapist, $serviceId, $date);
        }

        return response()->json(
            $eligibles->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'invitee_status' => $user->invitee_status ?? 'none',
            ])->values()
        );
    }

    public function cancel(Request $request, ScheduleSubRequest $subRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $subRequest);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        try {
            $this->subRequestService->cancel($therapist, $subRequest);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['sub_request' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('SubRequestController::cancel failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['sub_request' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Sub request cancelled.']);
        }

        return redirect()
            ->route('therapist.sub-requests.index')
            ->with('status', 'Sub request cancelled.');
    }
}
