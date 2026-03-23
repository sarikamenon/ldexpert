<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\PositionStatus;
use App\Models\Position;

final class PositionRowTransformer
{
    /**
     * @return array<int, string> 4 cell HTML strings in column order
     */
    public static function transform(Position $position): array
    {
        $editUrl = route('admin.positions.edit', $position);

        $servicesBadges = $position->services->isNotEmpty()
            ? $position->services
                ->map(fn ($service): string => '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-foreground border border-secondary/20">'.e($service->name).'</span>')
                ->implode(' ')
            : '<span class="text-sm text-foreground/50">No services</span>';

        $isActive = $position->status === PositionStatus::ACTIVE;
        $statusLabel = $position->status->label();
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '
            .($isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-secondary/10 text-foreground border border-secondary/20').'"
        >'.e($statusLabel).'</span>';

        $toggleAttrs = ['data-position' => (int) $position->id, 'data-status' => e($position->status->value), 'class' => 'toggle-position-status'];
        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate Position', $toggleAttrs)
            : ActionButtons::activate('Activate Position', $toggleAttrs);

        $actions = ActionButtons::wrap(
            ActionButtons::edit($editUrl, 'Edit Position'),
            $toggleBtn,
        );

        return [
            e($position->name),
            '<div class="flex flex-wrap gap-1">'.$servicesBadges.'</div>',
            $statusBadge,
            $actions,
        ];
    }
}
