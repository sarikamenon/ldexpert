<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\MakeupAvailabilityRowTransformer;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\StoreMakeupAvailabilityRequest;
use App\Models\ScheduleMakeupAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class MakeupAvailabilityController extends Controller
{
    public function __construct(
        private readonly ScheduleMakeupAvailabilityRepositoryInterface $repository,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ScheduleMakeupAvailability::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $windows = $this->repository->listUpcomingForTherapist($therapist);

        return view('therapist.makeup-requests.availability.index', [
            'rows' => $windows->map(fn (ScheduleMakeupAvailability $w): array => MakeupAvailabilityRowTransformer::transform($w, $tz))->all(),
            'createUrl' => route('therapist.makeup-requests.availability.create'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ScheduleMakeupAvailability::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $tz = $this->timezoneService->resolveTimezone($therapist);
        $now = now()->setTimezone($tz);

        return view('therapist.makeup-requests.availability.create', [
            'formDefaults' => [
                'availability_date' => $now->toDateString(),
                'start_time' => $now->format('H:i'),
                'end_time' => $now->addHours(3)->format('H:i'),
            ],
        ]);
    }

    public function store(StoreMakeupAvailabilityRequest $request): RedirectResponse
    {
        $this->authorize('create', ScheduleMakeupAvailability::class);

        /** @var \App\Models\User $therapist */
        $therapist = $request->user();

        /** @var string $date */
        $date = $request->validated('availability_date');
        /** @var string $start */
        $start = $request->validated('start_time');
        /** @var string $end */
        $end = $request->validated('end_time');
        /** @var string|null $notes */
        $notes = $request->validated('notes');

        try {
            $startUtc = $this->timezoneService->parseUserLocalToUtc($date.' '.$start.':00', $therapist);
            $endUtc = $this->timezoneService->parseUserLocalToUtc($date.' '.$end.':00', $therapist);

            $this->repository->create(
                $therapist,
                $startUtc->toDateString(),
                $startUtc->format('H:i'),
                $endUtc->format('H:i'),
                $notes,
            );
        } catch (Throwable $e) {
            Log::error('MakeupAvailabilityController::store failed', ['exception' => $e]);

            return back()
                ->withInput()
                ->withErrors(['availability' => 'Unable to save availability window. Please try again.']);
        }

        return redirect()
            ->route('therapist.makeup-requests.availability.index')
            ->with('status', 'Availability window added.');
    }

    public function destroy(ScheduleMakeupAvailability $availability): RedirectResponse
    {
        $this->authorize('delete', $availability);

        try {
            $this->repository->delete($availability);
        } catch (Throwable $e) {
            Log::error('MakeupAvailabilityController::destroy failed', ['exception' => $e]);

            return back()->withErrors(['availability' => 'Unable to delete this window. Please try again.']);
        }

        return redirect()
            ->route('therapist.makeup-requests.availability.index')
            ->with('status', 'Availability window removed.');
    }
}
