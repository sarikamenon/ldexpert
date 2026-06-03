<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
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
        $tz = $log->displayTimezone();
        $sessionDate = $log->localDate($tz);
        $createdAt = $log->created_at ? \Carbon\Carbon::parse($log->created_at)->setTimezone($tz) : null;

        $dateTimeDate = $sessionDate->format('M d, Y');
        $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;
        $entryCreated = $createdAt ? $createdAt->format('M d, Y') : null;
        $entryDiff = DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt);

        $dateLine = e($dateTimeDate);
        if ($duration) {
            $dateLine .= ' <span class="text-foreground/60 font-normal">· '.e($duration).'</span>';
        }

        $dateCell = '<div class="flex flex-col">'
            .'<span class="text-foreground font-medium whitespace-nowrap">'.$dateLine.'</span>';
        if ($entryCreated || $entryDiff) {
            $dateCell .= '<span class="inline-flex flex-wrap items-center gap-1 text-xs text-foreground/50">';
            if ($entryCreated) {
                $dateCell .= 'Entry: '.e($entryCreated);
            }
            if ($entryDiff) {
                $dateCell .= '<span class="inline-flex items-center px-2 py-0.5 leading-none rounded-base font-medium bg-warning/10 text-warning border border-warning/20">'.e($entryDiff).'</span>';
            }
            $dateCell .= '</span>';
        }
        $dateCell .= '</div>';

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
            $therapistCell .= '<span class="text-xs text-foreground/60 mt-1">'.e($serviceName).'</span>';
        }
        $therapistCell .= '</div>';

        $amountsCell = '<div class="flex flex-col space-y-1">'
            .'<span class="text-xs text-foreground/60">School: <span class="text-foreground font-medium">'.e(self::formatCurrency($log->school_invoice_amount)).'</span></span>'
            .'<span class="text-xs text-foreground/60">Therapist: <span class="text-foreground font-medium">'.e(self::formatCurrency($log->therapist_billable_amount)).'</span></span>'
            .'</div>';

        $notesCell = self::notesCell($log->notes);

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
        $buttons = [ActionButtons::view($viewUrl, 'View')];

        if ($log->status === SessionLogStatus::SUBMITTED) {
            $buttons[] = ActionButtons::approve(route('admin.session-logs.approve', $log));
            $buttons[] = ActionButtons::sendBack(route('admin.session-logs.show', $log).'#send-back-form');
        }

        if ($log->status?->canDelete()) {
            $buttons[] = ActionButtons::delete(
                route('admin.session-logs.destroy', $log),
                'Delete',
                'Delete session log?',
                'This will remove the session log and make the session available to log again.',
            );
        }

        $actionsCell = ActionButtons::wrap(...$buttons);

        return [
            $dateCell,
            $studentSchoolCell,
            $therapistCell,
            $amountsCell,
            $notesCell,
            $statusCell,
            $actionsCell,
        ];
    }

    private static function notesCell(?string $notes): string
    {
        $notes = $notes !== null ? trim($notes) : '';
        if ($notes === '') {
            return '<span class="text-gray-500">-</span>';
        }

        return '<div class="notes-cell" data-notes-cell>'
            .'<div class="notes-text text-sm text-foreground/80" data-notes-text>'.e($notes).'</div>'
            .'<button type="button" class="notes-toggle hidden text-xs text-primary mt-1 hover:underline" data-notes-toggle aria-expanded="false">Read more</button>'
            .'</div>';
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
