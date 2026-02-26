<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;

final class SSARowTransformer
{
    /**
     * @return array<int, string> 7 cell HTML strings in column order
     */
    public static function transform(ServiceSupportAgreement $ssa): array
    {
        $adminShowUrl = route('admin.ssas.show', $ssa);
        $therapistShowUrl = route('therapist.ssas.show', $ssa);

        $idCell = '<a href="'.e($adminShowUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View SSA Details">'
            .(int) $ssa->id.'</a>';

        $studentPart = $ssa->student
            ? '<a href="'.e(route('admin.students.show', $ssa->student)).'" class="font-medium text-primary hover:underline">'
                .e($ssa->student->name).'</a>'
            : '<span class="font-medium text-foreground/50">Unknown Student</span>';

        $primaryService = $ssa->primaryService->name ?? '—';

        $additionalServices = '';
        if ($ssa->additionalServices->isNotEmpty()) {
            $badges = $ssa->additionalServices->map(
                fn ($service): string => '<span class="inline-flex items-center px-2 py-0.5 rounded-full bg-background/subtle text-xs font-medium text-foreground/70">'
                    .e($service->name).'</span>'
            )->implode('');
            $additionalServices = '<div class="mt-1 flex flex-wrap gap-1">'.$badges.'</div>';
        }

        $schoolCell = '';
        if ($ssa->student?->studentProfile?->school) {
            $school = $ssa->student->studentProfile->school;
            $schoolCell = '<a href="'.e(route('admin.schools.show', $school)).'" class="text-xs text-foreground/60 hover:text-primary mt-1">'
                .e($school->display_name).'</a>';
        }

        $studentServiceCell = '<div class="flex flex-col">'
            .$studentPart
            .'<span class="text-sm text-foreground/70">'.e($primaryService).'</span>'
            .$additionalServices
            .$schoolCell
            .'</div>';

        $therapistCell = '';
        if ($ssa->assignedTherapist) {
            $therapistCell = '<a href="'.e(route('admin.therapists.show', $ssa->assignedTherapist)).'" class="text-primary hover:underline">'
                .e($ssa->assignedTherapist->name).'</a>';
        } else {
            $therapistCell = '<span class="text-sm text-foreground/60">Unassigned</span>';
        }

        $dateRangeCell = '<div class="flex flex-col space-y-1">'
            .'<span class="text-sm text-foreground">'.e($ssa->start_date->format('M d, Y')).'</span>'
            .'<span class="text-sm text-foreground">'.e($ssa->end_date?->format('M d, Y') ?? '—').'</span>'
            .'</div>';

        $sessionDetailsCell = '<div class="flex flex-col space-y-1">'
            .'<div class="flex items-center gap-2"><span class="text-xs text-foreground/60 font-medium">Minutes:</span>'
            .'<span class="text-sm text-foreground">'.(int) $ssa->minutes_per_session.' x '.(int) $ssa->sessions_per_frequency.'</span></div>'
            .'<div class="flex items-center gap-2"><span class="text-xs text-foreground/60 font-medium">Frequency:</span>'
            .'<span class="text-sm text-foreground">'.e($ssa->frequency?->label() ?? '—').'</span></div>'
            .'</div>';

        $tho = number_format((float) $ssa->tho_minutes);
        $served = number_format((float) $ssa->served_minutes);

        $minutesStatusCell = '<div class="flex flex-col space-y-2">'
            .'<div class="flex flex-col space-y-1">'
            .'<div class="flex items-center gap-2"><span class="text-xs text-foreground/60 font-medium">THO:</span>'
            .'<span class="text-sm text-foreground font-medium">'.$tho.'</span></div>'
            .'<div class="flex items-center gap-2"><span class="text-xs text-foreground/60 font-medium">Served:</span>'
            .'<span class="text-sm text-foreground">'.$served.'</span></div>'
            .'</div>';

        $statusVariant = match ($ssa->status) {
            SSAStatus::ACTIVE => 'success',
            SSAStatus::PENDING => 'warning',
            SSAStatus::COMPLETED => 'primary',
            SSAStatus::DEACTIVATED => 'secondary',
        };

        $minutesStatusCell .= '<div><span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '
            .match ($statusVariant) {
                'success' => 'bg-success/10 text-success border border-success/20',
                'warning' => 'bg-warning/10 text-warning border border-warning/20',
                'primary' => 'bg-primary/10 text-primary border border-primary/20',
                default => 'bg-secondary/10 text-foreground border border-secondary/20',
            }
        .'">'.e($ssa->status->label()).'</span></div></div>';

        $actionsCell = ActionButtons::wrap(
            ActionButtons::view($adminShowUrl, 'View SSA'),
        );

        return [
            $idCell,
            $studentServiceCell,
            $therapistCell,
            $dateRangeCell,
            $sessionDetailsCell,
            $minutesStatusCell,
            $actionsCell,
        ];
    }
}
