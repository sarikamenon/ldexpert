<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Schedule\Makeup\Services\ScheduleMakeupResponseService;
use App\Exceptions\MakeupResponseNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\ScheduleMakeupRequest;
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
    ) {}

    /**
     * GET — landing page when the parent clicks "Request Make-Up".
     */
    public function request(string $token): View
    {
        $batch = $this->resolveOrAbort($token);

        try {
            $updated = $this->responseService->recordParentRequest($batch);

            return view('public.makeup-response.request-recorded', ['batch' => $updated]);
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        } catch (Throwable $e) {
            $this->logFailure($token, $batch, $e);

            return view('public.makeup-response.error', ['batch' => $batch]);
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

            return view('public.makeup-response.declined', ['batch' => $updated]);
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        } catch (Throwable $e) {
            $this->logFailure($token, $batch, $e);

            return view('public.makeup-response.error', ['batch' => $batch]);
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
        return match ($reason) {
            MakeupResponseNotAllowedException::REASON_ALREADY_RESPONDED => view('public.makeup-response.already-responded', ['batch' => $batch]),
            MakeupResponseNotAllowedException::REASON_DEADLINE_PASSED => view('public.makeup-response.deadline-passed', ['batch' => $batch]),
            MakeupResponseNotAllowedException::REASON_EVENT_PAST => view('public.makeup-response.event-past', ['batch' => $batch]),
            default => view('public.makeup-response.error', ['batch' => $batch]),
        };
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
