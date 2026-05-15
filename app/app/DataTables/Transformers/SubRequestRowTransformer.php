<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Domain\Time\UserTimezoneService;
use App\Models\ScheduleSubRequest;

final class SubRequestRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleSubRequest $subRequest): array
    {
        $schedule = $subRequest->schedule;

        // Date / time — convert UTC start_time to the viewer's timezone
        $viewerTz = app(UserTimezoneService::class)->resolveTimezone(auth()->user());
        $localStart = $schedule?->localStart($viewerTz);

        $date = $localStart ? $localStart->format('M d, Y') : '—';
        $startTime = $localStart ? $localStart->format(config('display.time')) : '—';

        $dateTimeCell = '<div class="flex flex-col space-y-1">'
            .'<span class="text-foreground font-medium">'.e($date).'</span>'
            .'<span class="text-sm text-foreground/70">'.e($startTime).'</span>'
            .'</div>';

        // Student
        $studentName = $schedule !== null ? ($schedule->student?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull
        $schoolName = $schedule !== null ? $schedule->school?->display_name : null;
        $studentCell = '<div class="flex flex-col">'
            .'<span class="font-medium text-foreground">'.e($studentName).'</span>';
        if ($schoolName) {
            $studentCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        $studentCell .= '</div>';

        // Service
        $serviceName = $schedule !== null ? ($schedule->service?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull

        $serviceCell = '<span class="text-sm text-foreground">'.e($serviceName).'</span>';

        // Requester
        $requesterName = $subRequest->requestedBy?->name ?? '—'; // @phpstan-ignore nullsafe.neverNull
        $requesterCell = '<span class="text-sm text-foreground">'.e($requesterName).'</span>';

        // Reason
        $reason = $subRequest->reason;
        $reasonCell = $reason
            ? '<span class="text-sm text-foreground/80 break-words max-w-xs">'.e($reason).'</span>'
            : '<span class="text-foreground/40">—</span>';

        // Accept button (POST form)
        $acceptUrl = route('therapist.sub-requests.accept', $subRequest);
        $acceptCsrf = csrf_field();
        $acceptButton = '<form method="POST" action="'.e($acceptUrl).'" class="inline" data-accept-sub-request>'
            .$acceptCsrf
            .'<button type="submit" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded bg-success text-success-foreground hover:bg-success/90 transition-colors" '
            .'aria-label="Accept sub request">'
            .'Accept'
            .'</button>'
            .'</form>';

        // Decline button — triggered via JS fetch; no wrapping form needed
        $declineUrl = route('therapist.sub-requests.decline', $subRequest);
        $declineButton = '<button type="button" '
            .'data-decline-url="'.e($declineUrl).'" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-border bg-background text-foreground hover:bg-muted transition-colors" '
            .'aria-label="Decline sub request">'
            .'Decline'
            .'</button>';

        $actionsCell = '<div class="flex items-center gap-2">'.$acceptButton.$declineButton.'</div>';

        return [
            $dateTimeCell,
            $studentCell,
            $serviceCell,
            $requesterCell,
            $reasonCell,
            $actionsCell,
        ];
    }
}
