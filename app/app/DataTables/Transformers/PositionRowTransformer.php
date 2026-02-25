<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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
        $statusLabel = $position->status->label() ?? 'Unknown';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '
            .($isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-secondary/10 text-foreground border border-secondary/20').'"
        >'.e($statusLabel).'</span>';

        $nextStatus = $isActive ? 'inactive' : 'active';
        $buttonLabel = $isActive ? 'Deactivate' : 'Activate';
        $buttonClass = $isActive
            ? 'bg-danger text-danger-foreground hover:bg-danger/90'
            : 'bg-success text-success-foreground hover:bg-success/90';

        $actions = '<div class="flex items-center gap-2">'
            .'<a href="'.e($editUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors">Edit</a>'
            .'<button type="button" class="toggle-position-status inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors '
            .$buttonClass
            .'" data-position="'.(int) $position->id.'" data-status="'.e($position->status->value).'">'
            .e($buttonLabel)
            .'</button>'
            .'</div>';

        return [
            e($position->name),
            '<div class="flex flex-wrap gap-1">'.$servicesBadges.'</div>',
            $statusBadge,
            $actions,
        ];
    }
}
