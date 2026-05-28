<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Schedule\Makeup\Presenters\MakeupRequestPresenter;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Schedule\Makeup\Services\ScheduleMakeupResponseService;
use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Exceptions\MakeupResponseNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\ScheduleMakeupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Unauthenticated endpoints invoked from the parent reminder email's
 * "Request Make-Up" and "Decline Make-Up" buttons.
 *
 * The `response_token` in the URL resolves to a **batch** of rows — every
 * missed session for the same (calendar event, student, therapist) shares one
 * token, so one click flips the whole batch.
 *
 * Routes are protected by Laravel's `signed` middleware (URL tamper-proofing)
 * and a per-IP throttle.
 */
final class ScheduleMakeupResponseController extends Controller
{
    public function __construct(
        private readonly ScheduleMakeupResponseService $responseService,
        private readonly MakeupRequestPresenter $presenter,
        private readonly TherapistMakeupNotificationService $notificationService,
        private readonly ScheduleMakeupAvailabilityRepositoryInterface $availabilityRepo,
    ) {}

    /**
     * GET — landing page when the parent clicks "Request Make-Up".
     */
    public function request(string $token): View
    {
        $batch = $this->resolveOrAbort($token);

        try {
            $updated = $this->responseService->recordParentRequest($batch);

            $this->notifyTherapistOnAccept($updated);

            return view('public.makeup-response.request-recorded', $this->viewData($updated));
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        } catch (Throwable $e) {
            $this->logFailure($token, $batch, $e);

            return view('public.makeup-response.error', $this->viewData($batch));
        }
    }

    /**
     * GET — landing page when the parent clicks "Decline Make-Up".
     */
    public function decline(string $token): View
    {
        $batch = $this->resolveOrAbort($token);

        try {
            $updated = $this->responseService->recordParentDecline($batch);

            $this->notifyTherapistOnDecline($updated);

            return view('public.makeup-response.declined', $this->viewData($updated));
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        } catch (Throwable $e) {
            $this->logFailure($token, $batch, $e);

            return view('public.makeup-response.error', $this->viewData($batch));
        }
    }

    /**
     * @return Collection<int, ScheduleMakeupRequest>
     */
    private function resolveOrAbort(string $token): Collection
    {
        $batch = $this->responseService->findBatchByToken($token);

        if ($batch->isEmpty()) {
            abort(404);
        }

        return $batch;
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function viewForReason(string $reason, Collection $batch): View
    {
        $data = $this->viewData($batch);

        return match ($reason) {
            MakeupResponseNotAllowedException::REASON_ALREADY_RESPONDED => view('public.makeup-response.already-responded', $data),
            MakeupResponseNotAllowedException::REASON_DEADLINE_PASSED => view('public.makeup-response.deadline-passed', $data),
            MakeupResponseNotAllowedException::REASON_EVENT_PAST => view('public.makeup-response.event-past', $data),
            default => view('public.makeup-response.error', $data),
        };
    }

    /**
     * Build the view payload — batch + a pre-formatted list of missed-session
     * labels rendered in the student's timezone by the presenter, plus a
     * pre-formatted response-deadline string consumed by the deadline-passed
     * view (Blade should not format dates).
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @return array{batch: Collection<int, ScheduleMakeupRequest>, sessionLabels: array<int, string>, responseByDate: ?string}
     */
    private function viewData(Collection $batch): array
    {
        return [
            'batch' => $batch,
            'sessionLabels' => $this->presenter->sessionLabels($batch),
            'responseByDate' => $batch->first()?->response_date?->format((string) config('display.date')),
        ];
    }

    /**
     * Email #2 (Path 2): therapist has no availability — notify them to schedule directly.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function notifyTherapistOnAccept(Collection $batch): void
    {
        $head = $batch->first();
        if ($head === null) {
            return;
        }

        $eventDates = $batch
            ->map(fn (ScheduleMakeupRequest $row): string => $row->event_date->toDateString())
            ->unique()
            ->values()
            ->all();

        /** @var \App\Models\User $therapist */
        $therapist = $head->therapist;

        if ($this->availabilityRepo->therapistHasAvailabilityForDates($therapist, $eventDates)) {
            return;
        }

        $this->notificationService->sendNoAvailabilityAccepted($head);
    }

    /**
     * Email #3: notify therapist that parent declined (non-private students only).
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function notifyTherapistOnDecline(Collection $batch): void
    {
        $head = $batch->first();
        if ($head === null) {
            return;
        }

        $this->notificationService->sendDeclinedNotification($head);
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function logFailure(string $token, Collection $batch, Throwable $e): void
    {
        Log::error('Failed to apply make-up response', [
            'token' => $token,
            'request_ids' => $batch->pluck('id')->all(),
            'error' => $e->getMessage(),
        ]);
    }
}
