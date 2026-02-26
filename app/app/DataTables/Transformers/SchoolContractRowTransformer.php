<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
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
        $startDate = $contract->start_date->format('M d, Y');
        $endDate = $contract->end_date->format('M d, Y');
        $servicesCount = (int) $contract->services->count();
        $statusLabel = $contract->status->label();
        $badgeClass = $contract->status === ContractStatus::ACTIVE
            ? 'bg-success/10 text-success border border-success/20'
            : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $statusToggleUrl = route('admin.contracts.schools.status', $contract);
        $nextStatus = $contract->status === ContractStatus::ACTIVE ? 'inactive' : 'active';
        $isContractActive = $contract->status === ContractStatus::ACTIVE;
        $toggleTitle = $isContractActive ? 'Deactivate contract' : 'Activate contract';
        $toggleAttrs = ['data-endpoint' => e($statusToggleUrl), 'data-next-status' => e($nextStatus), 'class' => 'contract-status-toggle'];
        $toggleBtn = $isContractActive
            ? ActionButtons::deactivate($toggleTitle, $toggleAttrs)
            : ActionButtons::activate($toggleTitle, $toggleAttrs);

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Contract'),
            ActionButtons::edit(route('admin.contracts.schools.edit', $contract), 'Edit Contract'),
            $toggleBtn,
        );

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
