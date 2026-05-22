<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\MakeupRequestRowTransformer;
use App\Domain\Schedule\Makeup\Presenters\MakeupRequestPresenter;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\DTOs\Schedule\Makeup\RecordMakeupResponseDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\DeclineMakeupRequest;
use App\Http\Requests\Therapist\MarkNotRequiredMakeupRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ScheduleMakeupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

final class MakeupRequestController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'event_date',
    ];

    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $repository,
        private readonly MakeupRequestPresenter $presenter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ScheduleMakeupRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $statusFilter = $this->parseStatus($request->query('status'));

        return view('therapist.makeup-requests.index', [
            'datatableUrl' => route('therapist.makeup-requests.data'),
            'statusFilter' => $statusFilter?->value,
            'statusOptions' => $this->statusOptions(),
            'totalCount' => $this->repository->countForTherapist($therapist),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleMakeupRequest::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $statusFilter = $this->parseStatus($request->input('filter_status'));

        $total = $this->repository->countForTherapist($therapist);
        $filtered = $this->repository->countForTherapist($therapist, $statusFilter);
        $page = $this->repository->pageForTherapist(
            $therapist,
            $statusFilter,
            $params->start,
            $params->length,
        );

        return $this->dataTablesResponse(
            $params,
            $total,
            $filtered,
            $page,
            static fn (ScheduleMakeupRequest $row): array => MakeupRequestRowTransformer::transform($row, $therapist),
        );
    }

    public function show(Request $request, ScheduleMakeupRequest $makeupRequest): View
    {
        $this->authorize('view', $makeupRequest);

        try {
            $makeupRequest->load([
                'schedule.service',
                'schedule.school',
                'schedule.ssa',
                'student',
                'calendarEvent',
                'respondedBy',
                'makeupSchedule',
            ]);

            /** @var \App\Models\User|null $viewer */
            $viewer = $request->user();
            $detail = $this->presenter->detail($makeupRequest, $viewer);
        } catch (Throwable $e) {
            Log::error('MakeupRequestController::show failed', [
                'makeup_request_id' => $makeupRequest->id,
                'exception' => $e,
            ]);
            abort(500, 'Unable to load make-up request details.');
        }

        return view('therapist.makeup-requests._detail', ['detail' => $detail]);
    }

    public function decline(DeclineMakeupRequest $request, ScheduleMakeupRequest $makeupRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('decline', $makeupRequest);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        /** @var string $reason */
        $reason = $request->validated('reason');

        try {
            DB::transaction(function () use ($makeupRequest, $therapist, $reason): void {
                $locked = $this->repository->findAndLock($makeupRequest->id);

                if (! ($locked->isPending() || $locked->isSent()) || $locked->isResponded()) {
                    throw new InvalidArgumentException('This request can no longer be declined.');
                }

                $dto = RecordMakeupResponseDTO::therapistDecline(
                    requestId: $locked->id,
                    therapistUserId: (int) $therapist->id,
                    reason: $reason,
                    now: CarbonImmutable::now(),
                );

                $this->repository->recordResponse($locked, $dto);
            });
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['makeup_request' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('MakeupRequestController::decline failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['makeup_request' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Make-up request declined.']);
        }

        return redirect()
            ->route('therapist.makeup-requests.index')
            ->with('status', 'Make-up request declined.');
    }

    public function markNotRequired(MarkNotRequiredMakeupRequest $request, ScheduleMakeupRequest $makeupRequest): JsonResponse|RedirectResponse
    {
        $this->authorize('markNotRequired', $makeupRequest);

        /** @var string $reason */
        $reason = $request->validated('reason');

        try {
            DB::transaction(function () use ($makeupRequest, $reason): void {
                $locked = $this->repository->findAndLock($makeupRequest->id);

                if (! $locked->isPending()) {
                    throw new InvalidArgumentException('Only pending requests can be marked as not required.');
                }

                $this->repository->markNotRequired($locked, $reason);
            });
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['makeup_request' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('MakeupRequestController::markNotRequired failed', ['exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'An unexpected error occurred.'], 500);
            }

            return back()->withErrors(['makeup_request' => 'An unexpected error occurred.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Marked as not required.']);
        }

        return redirect()
            ->route('therapist.makeup-requests.index')
            ->with('status', 'Marked as not required.');
    }

    public function book(Request $request, ScheduleMakeupRequest $makeupRequest): RedirectResponse
    {
        $this->authorize('book', $makeupRequest);

        $ssaId = $makeupRequest->schedule?->ssa_id;

        if ($ssaId === null) {
            return back()->with('status', 'Cannot start booking — original schedule has no SSA on file.');
        }

        return redirect()->route('therapist.schedule.create', [
            'ssa_id' => $ssaId,
            'date' => $makeupRequest->event_date->toDateString(),
            'makeup_request_id' => $makeupRequest->id,
        ]);
    }

    private function parseStatus(mixed $raw): ?ScheduleMakeupRequestStatus
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return ScheduleMakeupRequestStatus::tryFrom($raw);
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(ScheduleMakeupRequestStatus::cases())
            ->mapWithKeys(static fn (ScheduleMakeupRequestStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
