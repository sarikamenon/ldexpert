<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\ContractStatus;
use App\Models\SchoolContract;

final class SchoolContractRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(SchoolContract $contract): array
    {
        $showUrl = route('admin.contracts.schools.show', $contract);
        $idCell = '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View Contract">'.(int) $contract->id.'</a>';
        $schoolCell = '—';
        if ($contract->school) {
            $schoolShowUrl = route('admin.schools.show', $contract->school);
            $schoolCell = '<a href="'.e($schoolShowUrl).'" class="text-primary hover:underline font-medium">'.e($contract->school->display_name ?? '—').'</a>';
        }
        $startDate = $contract->start_date ? $contract->start_date->format('M d, Y') : '—';
        $endDate = $contract->end_date ? $contract->end_date->format('M d, Y') : '—';
        $servicesCount = (int) $contract->services->count();
        $statusValue = $contract->status !== null ? (string) $contract->status->value : null;
        $statusLabel = $contract->status?->label() ?? '—';
        $badgeClass = $statusValue === ContractStatus::ACTIVE->value
            ? 'bg-success/10 text-success border border-success/20'
            : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $statusToggleUrl = route('admin.contracts.schools.status', $contract);
        $nextStatus = $statusValue === ContractStatus::ACTIVE->value ? 'inactive' : 'active';
        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $iconEdit = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        $toggleTitle = $nextStatus === 'active' ? 'Activate contract' : 'Deactivate contract';
        $actions = '<div class="flex space-x-1">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors" title="View Contract">'.$iconView.'</a>'
            .'<a href="'.e(route('admin.contracts.schools.edit', $contract)).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors" title="Edit Contract">'.$iconEdit.'</a>'
            .'<button type="button" class="contract-status-toggle inline-flex items-center justify-center w-8 h-8 rounded transition-colors bg-warning text-warning-foreground hover:bg-warning/90" data-endpoint="'.e($statusToggleUrl).'" data-next-status="'.e($nextStatus).'" title="'.e($toggleTitle).'">'
            .'<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13 13a3 3 0 0 1-6 0"></path></svg></button>'
            .'</div>';

        return [
            $idCell,
            $schoolCell,
            e($startDate),
            e($endDate),
            (string) $servicesCount,
            $statusBadge,
            $actions,
        ];
    }
}
