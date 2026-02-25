<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\SessionLog;
use App\Support\DateHelper;

final class TherapistSessionLogRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(SessionLog $log): array
    {
        $sessionDate = $log->session_date;
        $createdAt = $log->created_at ? \Carbon\Carbon::parse($log->created_at) : null;

        $startTime = $log->start_time?->format('g:i A');
        $endTime = $log->end_time?->format('g:i A');
        $timeRange = $startTime && $endTime ? "{$startTime} - {$endTime}" : null;
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;

        $dateTimeCell = '<div class="flex flex-col space-y-1">';
        if ($sessionDate) {
            $dateTimeCell .= '<span class="text-foreground font-medium">'.e($sessionDate->format('M d, Y')).'</span>';
        }
        if ($timeRange) {
            $dateTimeCell .= '<span class="text-foreground">'.e($timeRange).'</span>';
        }
        if ($duration) {
            $dateTimeCell .= '<span class="text-xs text-foreground/60">'.e($duration).'</span>';
        }
        if (! $sessionDate && ! $timeRange && ! $duration) {
            $dateTimeCell .= '<span class="text-gray-500">-</span>';
        }
        $dateTimeCell .= '</div>';

        $studentName = $log->student?->name ?? null;
        $schoolName = $log->school?->display_name ?? null;
        $studentSchoolCell = '<div class="flex flex-col">';
        if ($studentName) {
            $studentSchoolCell .= '<span class="font-medium text-foreground">'.e($studentName).'</span>';
        }
        if ($schoolName) {
            $studentSchoolCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($schoolName).'</span>';
        }
        if (! $studentName && ! $schoolName) {
            $studentSchoolCell .= '<span class="text-gray-500">-</span>';
        }
        $studentSchoolCell .= '</div>';

        $serviceCell = e($log->service?->name ?? '—');

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
            $entryInfoCell .= '<span class="text-gray-500">-</span>';
        }
        $entryInfoCell .= '</div>';

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
        $actionsCell = '<div class="flex items-center gap-1">';
        $actionsCell .= '<a href="'.e($viewUrl).'" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors border border-border text-foreground hover:bg-background/subtle" title="View"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>';

        if ($log->status?->canEdit()) {
            $editUrl = route('therapist.session-logs.edit', $log);
            $submitUrl = route('therapist.session-logs.submit', $log);
            $token = e(csrf_token());
            $actionsCell .= '<a href="'.e($editUrl).'" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors bg-primary text-primary-foreground hover:bg-primary/90" title="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>';
            $actionsCell .= '<form method="POST" action="'.e($submitUrl).'" class="inline"><input type="hidden" name="_token" value="'.$token.'"><button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors border border-primary text-primary hover:bg-primary/10" title="Submit" data-confirm-title="Submit session?" data-confirm-text="Submit this session for approval." data-confirm-icon="question"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></button></form>';
        }
        $actionsCell .= '</div>';

        return [
            $dateTimeCell,
            $studentSchoolCell,
            $serviceCell,
            $entryInfoCell,
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
