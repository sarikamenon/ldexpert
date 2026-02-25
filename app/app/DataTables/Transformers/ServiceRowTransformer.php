<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Support\Str;

final class ServiceRowTransformer
{
    /**
     * @return array<int, string> 6 cell HTML strings in column order
     */
    public static function transform(Service $service): array
    {
        $editUrl = route('admin.services.edit', $service);

        $nameCell = '<div class="font-medium">'.e($service->name).'</div>'
            .'<p class="text-sm text-foreground/70">'.e(Str::limit((string) $service->description, 60)).'</p>';

        $frequencyDot = $service->is_frequency_service ? 'bg-success' : 'bg-border';
        $frequencyCell = '<span class="inline-flex items-center gap-1">'
            .'<span class="w-2 h-2 rounded-full '.$frequencyDot.'"></span>'
            .($service->is_frequency_service ? 'Yes' : 'No')
            .'</span>';

        $directDot = $service->is_direct_service ? 'bg-success' : 'bg-border';
        $groupDot = $service->is_group_service ? 'bg-primary' : 'bg-border';
        $billableDot = $service->is_billable ? 'bg-success' : 'bg-border';

        $flagsCell = '<div class="flex flex-col gap-1 text-xs">'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$directDot.'"></span>Direct</span>'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$groupDot.'"></span>Group</span>'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$billableDot.'"></span>Billable</span>'
            .'</div>';

        $durationCell = '—';
        if ($service->min_duration_minutes || $service->max_duration_minutes) {
            $durationCell = '<span class="text-sm">'
                .(string) ($service->min_duration_minutes ?? '—')
                .' - '
                .(string) ($service->max_duration_minutes ?? '—')
                .' mins</span>';
        }

        $isActive = $service->status === ServiceStatus::ACTIVE;
        $statusVariant = $isActive ? 'success' : 'secondary';
        $statusLabel = $service->status->label() ?? 'Unknown';
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '
            .($isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-secondary/10 text-foreground border border-secondary/20').'"
        >'.e($statusLabel).'</span>';

        $nextStatus = $isActive ? 'inactive' : 'active';
        $buttonLabel = $isActive ? 'Deactivate' : 'Activate';
        $buttonClass = $isActive
            ? 'bg-danger text-danger-foreground hover:bg-danger/90'
            : 'bg-success text-success-foreground hover:bg-success/90';

        $actionsCell = '<div class="flex items-center gap-2">'
            .'<a href="'.e($editUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors">Edit</a>'
            .'<button type="button" class="toggle-service-status inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors '
            .$buttonClass
            .'" data-service="'.(int) $service->id.'" data-status="'.e($service->status->value).'">'
            .e($buttonLabel)
            .'</button>'
            .'</div>';

        return [
            $nameCell,
            $frequencyCell,
            $flagsCell,
            $durationCell,
            $statusCell,
            $actionsCell,
        ];
    }
}
