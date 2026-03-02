<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;

final class SSAExpirationReportRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ServiceSupportAgreement $ssa): array
    {
        $today = Carbon::today();
        $endDate = $ssa->end_date;
        $daysDiff = $endDate ? (int) $today->diffInDays($endDate, false) : 0;

        $ssaLink = '<a href="'.e(route('admin.ssas.show', $ssa)).'" class="text-primary hover:underline">#'.$ssa->id.'</a>';
        $student = e($ssa->student->name ?? '—');
        $school = e($ssa->student?->studentProfile?->school->display_name ?? '—');
        $therapist = e($ssa->assignedTherapist->name ?? 'Unassigned');
        $service = e($ssa->primaryService->name ?? '—');
        $endDateFormatted = $endDate ? $endDate->format('M d, Y') : '—';

        $daysLabel = $daysDiff > 0
            ? '<span class="text-warning">'.$daysDiff.' days</span>'
            : '<span class="text-danger">'.abs($daysDiff).' days ago</span>';

        $statusBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.e($ssa->status->label()).'</span>';

        return [
            $ssaLink,
            $student,
            $school,
            $therapist,
            $service,
            $endDateFormatted,
            $daysLabel,
            $statusBadge,
        ];
    }
}
