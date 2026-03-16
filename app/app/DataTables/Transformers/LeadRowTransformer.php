<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Carbon\Carbon;

final class LeadRowTransformer
{
    /**
     * @return array<int, string> 9 cell HTML strings in column order
     */
    public static function transform(Lead $lead): array
    {
        $showUrl = route('admin.leads.show', $lead);
        $editUrl = route('admin.leads.edit', $lead);
        $school = $lead->school;

        $schoolCell = $school
            ? '<a href="'.e(route('admin.schools.show', $school)).'" class="text-primary hover:underline">'.e($school->display_name).'</a>'
            : '—';

        $sourceBadge = $lead->source
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-foreground border border-secondary/20">'.e($lead->source->label()).'</span>'
            : '—';

        $statusBadge = self::buildStatusBadge($lead->status);
        $followUpCell = self::buildFollowUpCell($lead);

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Lead'),
            ActionButtons::edit($editUrl, 'Edit Lead'),
        );

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Lead">'.(int) $lead->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($lead->full_name).'</a>',
            e($lead->email ?? '—'),
            $schoolCell,
            $sourceBadge,
            $statusBadge,
            $followUpCell,
            $lead->created_at?->format('Y-m-d') ?? '—',
            $actions,
        ];
    }

    private static function buildStatusBadge(LeadStatus $status): string
    {
        $badgeClass = match (true) {
            $status->isActive() => 'bg-info/10 text-info border border-info/20',
            $status === LeadStatus::ENROLLED => 'bg-success/10 text-success border border-success/20',
            $status->isNegativeTerminal() => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-foreground border border-secondary/20',
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($status->label()).'</span>';
    }

    private static function buildFollowUpCell(Lead $lead): string
    {
        if (! $lead->follow_up_date) {
            return '—';
        }

        $dateStr = $lead->follow_up_date->format('Y-m-d');
        $isOverdue = $lead->follow_up_date->lt(Carbon::today()) && $lead->status->isActive();

        if ($isOverdue) {
            return '<span class="text-danger font-medium" title="Overdue">'.e($dateStr).'</span>';
        }

        return e($dateStr);
    }
}
