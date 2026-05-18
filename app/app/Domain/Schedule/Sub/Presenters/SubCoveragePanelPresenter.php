<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Presenters;

use App\Models\Schedule;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class SubCoveragePanelPresenter
{
    /**
     * Build the view-model array consumed by the Sub Coverage panel partials.
     *
     * @return array{
     *   has_request: bool,
     *   is_open: bool,
     *   is_accepted: bool,
     *   is_cancelled: bool,
     *   reason: string|null,
     *   accepted_by_name: string|null,
     *   accepted_by_initials: string|null,
     *   accepted_at: string|null,
     *   requested_ago: string|null,
     *   session_in: string|null,
     *   status_summary: string|null,
     *   invitee_rows: Collection<int, array{name: string, status_label: string, status_variant: string, is_muted: bool}>,
     *   store_url: string,
     *   update_invitees_url: string|null,
     *   cancel_url: string|null,
     *   eligible_subs_url: string,
     * }
     */
    public function present(Schedule $schedule, string $viewerTimezone): array
    {
        $subRequest = $schedule->activeSubRequest;

        $acceptedByName = $subRequest?->acceptedBy?->name;
        $inviteeRows = $this->inviteeRows($subRequest);

        return [
            'has_request' => $subRequest !== null,
            'is_open' => $subRequest?->isOpen() ?? false,
            'is_accepted' => $subRequest?->isAccepted() ?? false,
            'is_cancelled' => $subRequest?->isCancelled() ?? false,
            'reason' => $subRequest?->reason,
            'accepted_by_name' => $acceptedByName,
            'accepted_by_initials' => $this->initialsFor($acceptedByName),
            'accepted_at' => $this->formatAcceptedAt($subRequest, $viewerTimezone),
            'requested_ago' => $subRequest?->created_at?->diffForHumans(),
            'session_in' => $this->sessionIn($schedule),
            'status_summary' => $this->statusSummary($inviteeRows),
            'invitee_rows' => $inviteeRows,
            'store_url' => route('therapist.sub-requests.store-for-schedule', $schedule),
            'update_invitees_url' => $subRequest ? route('therapist.sub-requests.invitees.update', $subRequest) : null,
            'cancel_url' => $subRequest ? route('therapist.sub-requests.cancel', $subRequest) : null,
            'eligible_subs_url' => $subRequest
                ? route('therapist.sub-requests.eligible-subs-for-request', $subRequest)
                : route('therapist.sub-requests.eligible-subs').'?schedule_id='.$schedule->id,
        ];
    }

    private function sessionIn(Schedule $schedule): ?string
    {
        $start = Carbon::parse($schedule->start_time);
        if ($start->isPast()) {
            return null;
        }

        return 'session '.$start->diffForHumans();
    }

    /**
     * @param  Collection<int, array{name: string, status_label: string, status_variant: string, is_muted: bool}>  $inviteeRows
     */
    private function statusSummary(Collection $inviteeRows): ?string
    {
        if ($inviteeRows->isEmpty()) {
            return null;
        }

        $counts = $inviteeRows->countBy('status_label');
        $parts = [];
        foreach (['Pending', 'Accepted', 'Declined', 'Withdrawn'] as $label) {
            $count = $counts->get($label, 0);
            if ($count > 0) {
                $parts[] = $count.' '.mb_strtolower($label);
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function initialsFor(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : null;
    }

    private function formatAcceptedAt(?ScheduleSubRequest $subRequest, string $viewerTimezone): ?string
    {
        if ($subRequest?->accepted_at === null) {
            return null;
        }

        return Carbon::parse($subRequest->accepted_at)
            ->setTimezone($viewerTimezone)
            ->format((string) config('display.datetime'));
    }

    /**
     * @return Collection<int, array{name: string, status_label: string, status_variant: string, is_muted: bool}>
     */
    private function inviteeRows(?ScheduleSubRequest $subRequest): Collection
    {
        if ($subRequest === null) {
            /** @var Collection<int, array{name: string, status_label: string, status_variant: string, is_muted: bool}> $empty */
            $empty = collect();

            return $empty;
        }

        return $subRequest->invitees->map(function (ScheduleSubRequestInvitee $invitee): array {
            $statusLabel = match ($invitee->status) {
                'invited' => 'Pending',
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'withdrawn' => 'Withdrawn',
                'superseded' => 'Superseded',
                'expired' => 'Expired',
                default => ucfirst($invitee->status),
            };
            $statusVariant = match ($invitee->status) {
                'invited' => 'warning',
                'accepted' => 'success',
                'declined', 'withdrawn', 'superseded', 'expired' => 'muted',
                default => 'muted',
            };
            $isMuted = in_array($invitee->status, ['declined', 'withdrawn', 'superseded', 'expired'], true);

            return [
                'name' => $invitee->therapist->name ?? '—',
                'status_label' => $statusLabel,
                'status_variant' => $statusVariant,
                'is_muted' => $isMuted,
            ];
        });
    }
}
