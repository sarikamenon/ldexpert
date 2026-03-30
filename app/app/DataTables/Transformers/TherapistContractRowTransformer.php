<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\ContractStatus;
use App\Models\TherapistContract;

final class TherapistContractRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(TherapistContract $contract): array
    {
        $showUrl = route('admin.contracts.therapists.show', $contract);
        $idCell = '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View Contract">'.(int) $contract->id.'</a>';
        $therapistCell = '—';
        if ($contract->therapist) {
            $therapistShowUrl = route('admin.therapists.show', $contract->therapist->user);
            $name = trim($contract->therapist->first_name.' '.$contract->therapist->last_name) ?: '—';
            $therapistCell = '<a href="'.e($therapistShowUrl).'" class="text-primary hover:underline font-medium">'.e($name).'</a>';
        }
        $startDate = $contract->start_date->format('M d, Y');
        $endDate = $contract->end_date->format('M d, Y');
        $servicesCount = (int) $contract->services->count();
        $statusLabel = $contract->status->label();
        $badgeClass = $contract->status === ContractStatus::ACTIVE
            ? 'bg-success/10 text-success border border-success/20'
            : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $statusToggleUrl = route('admin.contracts.therapists.status', $contract);
        $nextStatus = $contract->status === ContractStatus::ACTIVE ? 'inactive' : 'active';
        $isContractActive = $contract->status === ContractStatus::ACTIVE;
        $toggleTitle = $isContractActive ? 'Deactivate contract' : 'Activate contract';
        $toggleAttrs = ['data-endpoint' => e($statusToggleUrl), 'data-next-status' => e($nextStatus), 'class' => 'therapist-contract-status-toggle'];
        $toggleBtn = $isContractActive
            ? ActionButtons::deactivate($toggleTitle, $toggleAttrs)
            : ActionButtons::activate($toggleTitle, $toggleAttrs);

        $downloadBtn = '';
        if ($contract->document_path) {
            $downloadBtn = ActionButtons::download(
                route('admin.contracts.therapists.download-document', $contract),
                'Download Document',
            );
        }

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Contract'),
            ActionButtons::edit(route('admin.contracts.therapists.edit', $contract), 'Edit Contract'),
            $downloadBtn,
            $toggleBtn,
        );

        return [
            $idCell,
            $therapistCell,
            e($startDate),
            e($endDate),
            (string) $servicesCount,
            $statusBadge,
            $actions,
        ];
    }
}
