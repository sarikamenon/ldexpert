<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Domain\Time\UserTimezoneService;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;

final class MySubRequestRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleSubRequest $subRequest): array
    {
        $schedule = $subRequest->schedule;

        $viewerTz = app(UserTimezoneService::class)->resolveTimezone(auth()->user());
        $localStart = $schedule?->localStart($viewerTz);

        $date = $localStart ? $localStart->format('M d, Y') : '—';
        $startTime = $localStart ? $localStart->format(config('display.time')) : '—';

        $dateTimeCell = '<div class="flex flex-col space-y-1">'
            .'<span class="text-foreground font-medium">'.e($date).'</span>'
            .'<span class="text-sm text-foreground/70">'.e($startTime).'</span>'
            .'</div>';

        $studentName = $schedule !== null ? ($schedule->student?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull
        $schoolName = $schedule !== null ? $schedule->school?->display_name : null;
        $studentCell = '<div class="flex flex-col">'
            .'<span class="font-medium text-foreground">'.e($studentName).'</span>';
        if ($schoolName) {
            $studentCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        $studentCell .= '</div>';

        $serviceName = $schedule !== null ? ($schedule->service?->name ?? '—') : '—'; // @phpstan-ignore nullsafe.neverNull
        $serviceCell = '<span class="text-sm text-foreground">'.e($serviceName).'</span>';

        $inviteesCell = self::renderInvitees($subRequest);

        $reason = $subRequest->reason;
        $reasonCell = $reason
            ? '<span class="text-sm text-foreground/80 break-words max-w-xs">'.e($reason).'</span>'
            : '<span class="text-foreground/40">—</span>';

        $actionsCell = self::renderActions($subRequest);

        return [
            $dateTimeCell,
            $studentCell,
            $serviceCell,
            $inviteesCell,
            $reasonCell,
            $actionsCell,
        ];
    }

    private static function renderInvitees(ScheduleSubRequest $subRequest): string
    {
        if ($subRequest->invitees->isEmpty()) {
            return '<span class="text-foreground/40 text-sm">No invitees</span>';
        }

        $counts = $subRequest->invitees->countBy('status');
        $parts = [];
        $map = [
            'invited' => ['label' => 'pending', 'classes' => 'bg-warning/10 text-warning border border-warning/20'],
            'accepted' => ['label' => 'accepted', 'classes' => 'bg-success/10 text-success border border-success/20'],
            'declined' => ['label' => 'declined', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
            'withdrawn' => ['label' => 'withdrawn', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
            'expired' => ['label' => 'expired', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
        ];

        foreach ($map as $status => $meta) {
            $count = (int) $counts->get($status, 0);
            if ($count <= 0) {
                continue;
            }
            $parts[] = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$meta['classes'].'">'
                .e($count.' '.$meta['label']).'</span>';
        }

        $names = $subRequest->invitees
            ->map(fn (ScheduleSubRequestInvitee $i): string => $i->therapist->name ?? '—')
            ->filter()
            ->take(3)
            ->implode(', ');
        $remaining = $subRequest->invitees->count() - 3;
        if ($remaining > 0) {
            $names .= ' + '.$remaining.' more';
        }

        return '<div class="flex flex-col gap-1">'
            .'<div class="flex flex-wrap gap-1">'.implode('', $parts).'</div>'
            .'<span class="text-xs text-foreground/60">'.e($names).'</span>'
            .'</div>';
    }

    private static function renderActions(ScheduleSubRequest $subRequest): string
    {
        $schedule = $subRequest->schedule;
        $editUrl = $schedule !== null ? route('therapist.schedule.edit', $schedule->id) : '#';

        $viewButton = '<a href="'.e($editUrl).'" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-border bg-background text-foreground hover:bg-muted transition-colors" '
            .'aria-label="Manage sub request">'
            .'Manage'
            .'</a>';

        $buttons = $viewButton;

        if ($subRequest->isOpen()) {
            $cancelUrl = route('therapist.sub-requests.cancel', $subRequest);
            $buttons .= '<button type="button" '
                .'data-cancel-url="'.e($cancelUrl).'" '
                .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-danger/30 bg-background text-danger hover:bg-danger/10 transition-colors" '
                .'aria-label="Withdraw sub request">'
                .'Withdraw'
                .'</button>';
        }

        return '<div class="flex items-center gap-2">'.$buttons.'</div>';
    }
}
