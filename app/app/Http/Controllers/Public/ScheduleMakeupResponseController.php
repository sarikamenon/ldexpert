<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Schedule\Makeup\Presenters\MakeupRequestPresenter;
use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupAvailabilityRepositoryInterface;
use App\Domain\Schedule\Makeup\Services\MakeupBookingService;
use App\Domain\Schedule\Makeup\Services\MakeupSlotConflictException;
use App\Domain\Schedule\Makeup\Services\ScheduleMakeupResponseService;
use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Domain\Time\UserTimezoneService;
use App\DTOs\Schedule\Makeup\MakeupSlotPickDTO;
use App\Exceptions\MakeupResponseNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\ScheduleMakeupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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
        private readonly MakeupBookingService $bookingService,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    /**
     * GET — landing page when the parent clicks "Request Make-Up".
     *
     * Path 1 (therapist has availability): show the sub-slot picker.
     * Path 2 (no availability): record acceptance as REQUESTED + notify therapist.
     */
    public function request(string $token): View
    {
        $batch = $this->resolveOrAbort($token);

        try {
            $this->responseService->guardCanRespond($batch);
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        }

        $eventDates = $this->eventDates($batch);

        /** @var ScheduleMakeupRequest $head */
        $head = $batch->first();
        /** @var \App\Models\User $therapist */
        $therapist = $head->therapist;

        if ($this->availabilityRepo->therapistHasAvailabilityForDates($therapist, $eventDates)) {
            return $this->showSlotPicker($batch, $token);
        }

        return $this->handlePath2($batch, $token);
    }

    /**
     * POST — parent submits their sub-slot picks (Path 1).
     */
    public function pickSlots(Request $request, string $token): View
    {
        $batch = $this->resolveOrAbort($token);

        try {
            $this->responseService->guardCanRespond($batch);
        } catch (MakeupResponseNotAllowedException $e) {
            return $this->viewForReason($e->reason, $batch);
        }

        /** @var array<string, string>|mixed $rawSlots */
        $rawSlots = $request->input('slots', []);
        $slots = is_array($rawSlots) ? $rawSlots : [];

        if ($slots === []) {
            return $this->showSlotPicker($batch, $token, 'Please select a time slot for each missed session.');
        }

        $parentUserId = $this->resolveParentUserId($batch);

        try {
            /** @var array<int, ScheduleMakeupRequest> $bookedArr */
            $bookedArr = [];
            /** @var array<int, ScheduleMakeupRequest> $remainingArr */
            $remainingArr = [];

            foreach ($batch as $row) {
                /** @var ScheduleMakeupRequest $row */
                $slotKey = (string) $row->id;
                if (! isset($slots[$slotKey]) || $slots[$slotKey] === '') {
                    $remainingArr[] = $row;

                    continue;
                }

                $schedule = $row->schedule;
                if ($schedule === null) {
                    $remainingArr[] = $row;

                    continue;
                }

                $startUtc = CarbonImmutable::parse($slots[$slotKey], 'UTC');
                $endUtc = $startUtc->addMinutes($schedule->durationMinutes());

                $pick = new MakeupSlotPickDTO(
                    makeupRequestId: $row->id,
                    startUtc: $startUtc,
                    endUtc: $endUtc,
                );

                $updated = $this->bookingService->bookSlot($row, $pick, $parentUserId);
                $bookedArr[] = $updated;

                $this->notifyTherapistOnBooking($updated);
            }

            if ($bookedArr === []) {
                return $this->showSlotPicker($batch, $token, 'Please select a time slot for each missed session.');
            }

            $booked = new Collection($bookedArr);
            $remaining = new Collection($remainingArr);

            $this->markRespondedIfNeeded($batch, $parentUserId);

            return view('public.makeup-response.slots-booked', $this->bookedViewData($booked, $remaining));
        } catch (MakeupSlotConflictException $e) {
            return $this->showSlotPicker($batch, $token, $e->getMessage());
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
     * Path 1: Render the sub-slot picker page.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function showSlotPicker(Collection $batch, string $token, ?string $error = null): View
    {
        $head = $batch->first();
        if ($head === null) {
            abort(404);
        }

        /** @var \App\Models\User $student */
        $student = $head->student;
        $studentTz = $this->timezoneService->resolveTimezone($student);
        $timeFormat = (string) config('display.time');
        $dateFormat = (string) config('display.date');

        /** @var array<int, array{request: ScheduleMakeupRequest, label: string, slots: array<int, array{value: string, label: string}>}> $rows */
        $rows = [];

        foreach ($batch as $row) {
            /** @var ScheduleMakeupRequest $row */
            if ($row->status->value === 'scheduled') {
                continue;
            }

            $startTimes = $this->bookingService->availableStartTimes($row);
            $schedule = $row->schedule;
            $duration = $schedule?->durationMinutes() ?? 30;

            $formattedSlots = [];
            foreach ($startTimes as $startUtc) {
                $localStart = $startUtc->setTimezone($studentTz);
                $localEnd = $localStart->addMinutes($duration);
                $formattedSlots[] = [
                    'value' => $startUtc->format('Y-m-d H:i:s'),
                    'label' => sprintf(
                        '%s, %s – %s',
                        $localStart->format($dateFormat),
                        $localStart->format($timeFormat),
                        $localEnd->format($timeFormat),
                    ),
                ];
            }

            $rows[] = [
                'request' => $row,
                'label' => $this->presenter->sessionLabel($row),
                'slots' => $formattedSlots,
            ];
        }

        $submitUrl = $this->signedPickSlotsUrl($token);

        return view('public.makeup-response.slot-picker', [
            'batch' => $batch,
            'rows' => $rows,
            'therapistName' => $head->therapist->name ?? '—',
            'responseByDate' => $head->response_date->format($dateFormat),
            'submitUrl' => $submitUrl,
            'error' => $error,
            'studentTimezone' => $this->timezoneAbbreviation($studentTz),
        ]);
    }

    /**
     * Path 2: No availability — record acceptance and notify therapist.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function handlePath2(Collection $batch, string $token): View
    {
        try {
            $updated = $this->responseService->recordParentRequest($batch);

            $this->notifyTherapistOnAcceptNoAvailability($updated);

            return view('public.makeup-response.request-recorded', $this->viewData($updated));
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
     * @param  Collection<int, ScheduleMakeupRequest>  $booked
     * @param  Collection<int, ScheduleMakeupRequest>  $remaining
     * @return array<string, mixed>
     */
    private function bookedViewData(Collection $booked, Collection $remaining): array
    {
        /** @var ScheduleMakeupRequest $head */
        $head = $booked->first();
        $studentTz = $this->timezoneService->resolveTimezone($head->student);
        $dateFormat = (string) config('display.date');
        $timeFormat = (string) config('display.time');

        $bookedLabels = $booked->map(function (ScheduleMakeupRequest $row) use ($studentTz, $dateFormat, $timeFormat): string {
            $schedule = $row->schedule;
            if ($schedule === null) {
                return $row->event_date->format($dateFormat);
            }

            $start = $schedule->localStart($studentTz);
            $end = $schedule->localEnd($studentTz);

            return sprintf(
                '%s, %s – %s',
                $start->format($dateFormat),
                $start->format($timeFormat),
                $end->format($timeFormat),
            );
        })->values()->all();

        return [
            'booked' => $booked,
            'remaining' => $remaining,
            'bookedLabels' => $bookedLabels,
            'therapistName' => $head->therapist->name ?? '—',
        ];
    }

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     * @return array<int, string>
     */
    private function eventDates(Collection $batch): array
    {
        return $batch
            ->map(fn (ScheduleMakeupRequest $row): string => $row->event_date->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve parent user id from the batch's student chain.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function resolveParentUserId(Collection $batch): int
    {
        $head = $batch->first();

        if ($head === null) {
            return 0;
        }

        return (int) $head->student_id;
    }

    /**
     * For Path 1 bookings, mark responded_at/responded_by on rows that were
     * not already stamped by bookSlot (which goes through linkBookedSchedule).
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function markRespondedIfNeeded(Collection $batch, int $parentUserId): void
    {
        $now = CarbonImmutable::now();

        foreach ($batch as $row) {
            /** @var ScheduleMakeupRequest $row */
            if ($row->responded_at !== null) {
                continue;
            }

            $row->fill([
                'responded_at' => $now->toDateTimeString(),
                'responded_by_type' => 'parent',
                'responded_by_user_id' => $parentUserId,
                'response_source' => 'email_link',
            ]);
            $row->save();
        }
    }

    private function notifyTherapistOnBooking(ScheduleMakeupRequest $request): void
    {
        $schedule = $request->schedule;
        if ($schedule === null) {
            return;
        }

        $studentTz = $this->timezoneService->resolveTimezone($request->student);
        $start = $schedule->localStart($studentTz);
        $end = $schedule->localEnd($studentTz);
        $dateFormat = (string) config('display.date');
        $timeFormat = (string) config('display.time');

        $scheduledDateTime = sprintf(
            '%s, %s – %s',
            $start->format($dateFormat),
            $start->format($timeFormat),
            $end->format($timeFormat),
        );

        $this->notificationService->sendMakeupScheduled($request, $scheduledDateTime);
    }

    /**
     * Email #2 (Path 2): therapist has no availability.
     *
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    private function notifyTherapistOnAcceptNoAvailability(Collection $batch): void
    {
        $head = $batch->first();
        if ($head === null) {
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

    private function signedPickSlotsUrl(string $token): string
    {
        return (string) \Illuminate\Support\Facades\URL::signedRoute('makeup-response.pick-slots', ['token' => $token]);
    }

    private function timezoneAbbreviation(string $timezone): string
    {
        $label = \App\Constants\UsTimezones::getTimezoneLabel($timezone);

        if (preg_match('/\(([^)]+)\)\s*$/', $label, $matches) === 1) {
            return $matches[1];
        }

        return $timezone;
    }
}
