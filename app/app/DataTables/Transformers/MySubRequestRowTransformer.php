<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\SubRequestInviteeStatus;
use App\Models\ScheduleSubRequest;
use App\Models\ScheduleSubRequestInvitee;

final class MySubRequestRowTransformer extends SubRequestRowBase
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleSubRequest $subRequest): array
    {
        return [
            self::dateTimeCell($subRequest),
            self::studentCell($subRequest),
            self::serviceCell($subRequest),
            self::inviteesCell($subRequest),
            self::reasonCell($subRequest),
            self::actionsCell($subRequest),
        ];
    }

    private static function inviteesCell(ScheduleSubRequest $subRequest): string
    {
        if ($subRequest->invitees->isEmpty()) {
            return '<span class="text-foreground/40 text-sm">No invitees</span>';
        }

        $counts = $subRequest->invitees->countBy(fn (ScheduleSubRequestInvitee $i): string => $i->status->value);
        $map = [
            SubRequestInviteeStatus::INVITED->value => ['label' => 'pending', 'classes' => 'bg-warning/10 text-warning border border-warning/20'],
            SubRequestInviteeStatus::ACCEPTED->value => ['label' => 'accepted', 'classes' => 'bg-success/10 text-success border border-success/20'],
            SubRequestInviteeStatus::DECLINED->value => ['label' => 'declined', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
            SubRequestInviteeStatus::WITHDRAWN->value => ['label' => 'withdrawn', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
            SubRequestInviteeStatus::EXPIRED->value => ['label' => 'expired', 'classes' => 'bg-muted/50 text-foreground/50 border border-border'],
        ];

        $parts = [];
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

    private static function actionsCell(ScheduleSubRequest $subRequest): string
    {
        $schedule = $subRequest->schedule;
        $editUrl = $schedule !== null ? route('therapist.schedule.edit', $schedule->id) : '#';

        $buttons = '<a href="'.e($editUrl).'" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-border bg-background text-foreground hover:bg-muted transition-colors" '
            .'aria-label="Manage sub request">'
            .'Manage'
            .'</a>';

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
