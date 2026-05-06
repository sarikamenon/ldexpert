<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
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

        $colorDot = $service->color
            ? '<span class="inline-block w-4 h-4 rounded-full border border-border shadow-sm flex-shrink-0 mt-0.5" style="background-color:'.e($service->color).'"></span>'
            : '<span class="inline-block w-4 h-4 rounded-full border border-border bg-border flex-shrink-0 mt-0.5"></span>';

        $nameCell = '<div class="flex items-start gap-3">'
            .$colorDot
            .'<div class="min-w-0">'
            .'<span class="font-medium">'.e($service->name).'</span>'
            .'<p class="text-sm text-foreground/70 mt-0.5">'.e(Str::limit((string) $service->description, 60)).'</p>'
            .'</div>'
            .'</div>';

        $frequencyDot = $service->is_frequency_service ? 'bg-success' : 'bg-border';
        $frequencyCell = '<span class="inline-flex items-center gap-1">'
            .'<span class="w-2 h-2 rounded-full '.$frequencyDot.'"></span>'
            .($service->is_frequency_service ? 'Yes' : 'No')
            .'</span>';

        $directDot = $service->is_direct_service ? 'bg-success' : 'bg-border';
        $groupDot = $service->is_group_service ? 'bg-primary' : 'bg-border';
        $billableDot = $service->is_billable ? 'bg-success' : 'bg-border';

        $emailDot = $service->send_email ? 'bg-success' : 'bg-border';
        $emailRow = ! $service->is_direct_service
            ? '<span class="inline-flex items-center gap-1">'
                .'<span class="w-2 h-2 rounded-full '.$emailDot.'"></span>'
                .'Email'
                .'</span>'
            : '';

        $flagsCell = '<div class="flex flex-col gap-1 text-xs">'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$directDot.'"></span>Direct</span>'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$groupDot.'"></span>Group</span>'
            .'<span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full '.$billableDot.'"></span>Billable</span>'
            .($emailRow ? $emailRow : '')
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
        $statusLabel = $service->status->label();
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '
            .($isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-secondary/10 text-foreground border border-secondary/20').'"
        >'.e($statusLabel).'</span>';

        $toggleAttrs = ['data-service' => (int) $service->id, 'data-status' => e($service->status->value), 'class' => 'toggle-service-status'];
        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate Service', $toggleAttrs)
            : ActionButtons::activate('Activate Service', $toggleAttrs);

        $actionsCell = ActionButtons::wrap(
            ActionButtons::edit($editUrl, 'Edit Service'),
            $toggleBtn,
        );

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
