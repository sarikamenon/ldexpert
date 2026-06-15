<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\SessionLog;
use App\Support\DateHelper;

final class TherapistSessionLogRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(SessionLog $log): array
    {
        $tz = $log->displayTimezone();
        $localStart = $log->localStart($tz);
        $localEnd = $log->localEnd($tz);
        $sessionDate = $localStart;
        $createdAt = $log->created_at ? \Carbon\Carbon::parse($log->created_at)->setTimezone($tz) : null;

        $startTime = $localStart->format(config('display.time'));
        $endTime = $localEnd->format(config('display.time'));
        $timeRange = "{$startTime} - {$endTime}";
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;

        $dateTimeCell = '<div class="flex flex-col space-y-1">'
            .'<span class="text-foreground font-medium">'.e($sessionDate->format('M d, Y')).'</span>'
            .'<span class="text-foreground">'.e($timeRange).'</span>';
        if ($duration) {
            $dateTimeCell .= '<span class="text-xs text-foreground/60">'.e($duration).'</span>';
        }
        $dateTimeCell .= '</div>';

        $entryCreated = $createdAt ? $createdAt->format('M d, Y') : null;
        $entryDiff = DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt);
        $entryInfoCell = '<div class="flex flex-col space-y-1">';
        if ($entryCreated) {
            $entryInfoCell .= '<span class="text-xs text-foreground/60">'.e($entryCreated).'</span>';
        }
        if ($entryDiff) {
            $entryInfoCell .= '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-warning/10 text-warning border border-warning/20 w-fit">'.e($entryDiff).'</span>';
        }
        if (! $entryCreated && ! $entryDiff) {
            $entryInfoCell .= '<span class="text-foreground/40">-</span>';
        }
        $entryInfoCell .= '</div>';

        $studentName = $log->student->name ?? null;
        $schoolName = $log->school->display_name ?? null;
        $studentSchoolCell = '<div class="flex flex-col">';
        if ($studentName) {
            $studentSchoolCell .= '<span class="font-medium text-foreground">'.e($studentName).'</span>';
        }
        if ($schoolName) {
            $studentSchoolCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        if (! $studentName && ! $schoolName) {
            $studentSchoolCell .= '<span class="text-foreground/40">-</span>';
        }
        $studentSchoolCell .= '</div>';

        $serviceName = $log->service->name ?? null;
        $therapistServiceCell = '<div class="flex flex-col">';
        $therapistServiceCell .= '<span class="font-medium text-foreground">'.e($log->therapist->name ?? '-').'</span>';
        if ($serviceName) {
            $therapistServiceCell .= '<span class="text-sm text-foreground/70 mt-0.5">'.e($serviceName).'</span>';
        }
        $therapistServiceCell .= '</div>';

        $schoolAmountCell = self::formatCurrency($log->school_invoice_amount);
        $therapistAmountCell = self::formatCurrency($log->therapist_billable_amount);

        $statusLabel = self::getStatusLabel($log);
        $statusVariant = match ($statusLabel) {
            'Approved' => 'success',
            'Submitted', 'Sent back' => 'warning',
            'Cancelled' => 'danger',
            default => 'secondary',
        };
        $badgeClass = match ($statusVariant) {
            'success' => 'bg-success/10 text-success border border-success/20',
            'warning' => 'bg-warning/10 text-warning border border-warning/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-secondary/10 text-secondary border border-secondary/20',
        };
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';

        $viewUrl = route('therapist.session-logs.show', $log);
        $buttons = [ActionButtons::view($viewUrl, 'View')];

        if ($log->status?->canEdit()) {
            $buttons[] = ActionButtons::edit(route('therapist.session-logs.edit', $log), 'Edit');
            $buttons[] = ActionButtons::submit(route('therapist.session-logs.submit', $log));
        }

        $actionsCell = ActionButtons::wrap(...$buttons);

        return [
            $dateTimeCell,
            $entryInfoCell,
            $studentSchoolCell,
            $therapistServiceCell,
            $schoolAmountCell,
            $therapistAmountCell,
            $statusCell,
            $actionsCell,
        ];
    }

    private static function formatCurrency(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }
        if (! is_numeric($amount)) {
            return '-';
        }

        return '$'.number_format((float) $amount, 2);
    }

    private static function getStatusLabel(SessionLog $log): string
    {
        try {
            return $log->status?->label() ?? '-';
        } catch (\Throwable) {
            return '-';
        }
    }
}
