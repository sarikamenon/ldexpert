<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Support\DateHelper;

final class SessionLogRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(SessionLog $log): array
    {
        $sessionDate = $log->session_date;
        $createdAt = $log->created_at ? \Carbon\Carbon::parse($log->created_at) : null;

        $dateTimeDate = $sessionDate ? $sessionDate->format('M d, Y') : null;
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;
        $dateTimeCell = '<div class="flex flex-col space-y-1">';
        if ($dateTimeDate) {
            $dateTimeCell .= '<span class="text-foreground font-medium">'.e($dateTimeDate).'</span>';
        }
        if ($duration) {
            $dateTimeCell .= '<span class="text-xs text-foreground/60">'.e($duration).'</span>';
        }
        if (! $dateTimeDate && ! $duration) {
            $dateTimeCell .= '<span class="text-gray-500">-</span>';
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
            $entryInfoCell .= '<span class="text-gray-500">-</span>';
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
            $studentSchoolCell .= '<span class="text-gray-500">-</span>';
        }
        $studentSchoolCell .= '</div>';

        $therapistName = $log->therapist->name ?? '-';
        $serviceName = $log->service->name ?? null;
        $therapistCell = '<div class="flex flex-col">';
        $therapistCell .= '<span class="font-medium text-foreground">'.e($therapistName).'</span>';
        if ($serviceName) {
            $therapistCell .= '<span class="text-sm text-foreground/70 mt-0.5">'.e($serviceName).'</span>';
        }
        $therapistCell .= '</div>';

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

        $viewUrl = route('admin.session-logs.show', $log);
        $actionsCell = '<div class="flex items-center gap-1">';
        $actionsCell .= '<a href="'.e($viewUrl).'" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors border border-border text-foreground hover:bg-background/subtle" title="View">';
        $actionsCell .= '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>';

        if ($log->status === SessionLogStatus::SUBMITTED) {
            $approveUrl = route('admin.session-logs.approve', $log);
            $cancelUrl = route('admin.session-logs.cancel', $log);
            $token = e(csrf_token());
            $actionsCell .= '<form method="POST" action="'.e($approveUrl).'" class="inline"><input type="hidden" name="_token" value="'.$token.'"><button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors bg-primary text-primary-foreground hover:bg-primary/90" title="Approve" data-confirm-title="Approve session?" data-confirm-text="This will mark the session as approved." data-confirm-icon="question"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></button></form>';
            $actionsCell .= '<form method="POST" action="'.e($cancelUrl).'" class="inline"><input type="hidden" name="_token" value="'.$token.'"><button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded transition-colors border border-primary text-primary hover:bg-primary/10" title="Cancel" data-confirm-title="Cancel session?" data-confirm-text="This will cancel the submitted session." data-confirm-icon="warning"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button></form>';
        }
        $actionsCell .= '</div>';

        return [
            $dateTimeCell,
            $entryInfoCell,
            $studentSchoolCell,
            $therapistCell,
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
