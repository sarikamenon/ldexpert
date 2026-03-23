<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ServiceSupportAgreement;

final class SSAUtilizationReportRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ServiceSupportAgreement $ssa): array
    {
        $tho = $ssa->tho_minutes ?? 0;
        $served = $ssa->served_minutes ?? 0;
        $utilization = $tho > 0 ? round(($served / $tho) * 100, 2) : 0;

        $badgeVariant = 'success';
        if ($utilization < 80) {
            $badgeVariant = 'danger';
        } elseif ($utilization > 120) {
            $badgeVariant = 'warning';
        }

        $ssaLink = '<a href="'.e(route('admin.ssas.show', $ssa)).'" class="text-primary hover:underline">#'.$ssa->id.'</a>';
        $student = e($ssa->student->name ?? '—');
        $school = e($ssa->student?->studentProfile?->school->display_name ?? '—');
        $therapist = e($ssa->assignedTherapist->name ?? 'Unassigned');
        $service = e($ssa->primaryService->name ?? '—');
        $thoFormatted = number_format($ssa->tho_hours, 2);
        $servedFormatted = number_format($ssa->served_hours, 2);
        $utilizationBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-'.$badgeVariant.'/10 text-'.$badgeVariant.'">'.$utilization.'%</span>';
        $statusBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.e($ssa->status->label()).'</span>';

        return [
            $ssaLink,
            $student,
            $school,
            $therapist,
            $service,
            $thoFormatted,
            $servedFormatted,
            $utilizationBadge,
            $statusBadge,
        ];
    }
}
