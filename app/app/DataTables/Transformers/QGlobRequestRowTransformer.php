<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\QGlobRequestStatus;
use App\Models\QGlobRequest;
use Carbon\Carbon;

final class QGlobRequestRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transformForAdmin(QGlobRequest $request): array
    {
        $dateCell = self::dateTimeCell($request);
        $therapistCell = e($request->requestedBy->name ?? '—');
        $studentCell = self::studentCell($request);
        $statusCell = self::statusCell($request->status);

        $viewUrl = route('admin.qglob-requests.show', ['qglob_request' => $request]);
        $actionsCell = ActionButtons::wrap(ActionButtons::view($viewUrl, 'View'));

        return [
            $dateCell,
            $therapistCell,
            $studentCell,
            $statusCell,
            $actionsCell,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function transformForTherapist(QGlobRequest $request): array
    {
        $dateCell = self::dateTimeCell($request);
        $studentCell = self::studentCell($request);
        $statusCell = self::statusCell($request->status);

        $viewUrl = route('therapist.qglob-requests.show', ['qglob_request' => $request]);
        $buttons = [ActionButtons::view($viewUrl, 'View')];

        if ($request->status === QGlobRequestStatus::PENDING) {
            $deleteUrl = route('therapist.qglob-requests.destroy', ['qglob_request' => $request]);
            $buttons[] = ActionButtons::delete(
                $deleteUrl,
                'Delete',
                'Delete request?',
                'This will permanently remove this QGlob request.',
            );
        }

        $actionsCell = ActionButtons::wrap(...$buttons);

        return [
            $dateCell,
            $studentCell,
            $statusCell,
            $actionsCell,
        ];
    }

    private static function dateTimeCell(QGlobRequest $request): string
    {
        $dateStr = $request->requested_date->format('M j, Y');
        $timeRaw = trim((string) $request->getAttribute('requested_time'));
        $dateForParse = $request->requested_date->format('Y-m-d');
        $timeStr = $timeRaw !== ''
            ? Carbon::parse($dateForParse.' '.$timeRaw)->format('g:i A')
            : '—';

        return '<div class="flex flex-col space-y-1">'
            .'<span class="text-foreground font-medium">'.e($dateStr).'</span>'
            .'<span class="text-sm text-foreground/80">'.e($timeStr).'</span>'
            .'</div>';
    }

    private static function studentCell(QGlobRequest $request): string
    {
        $name = $request->student?->name;
        $school = $request->student?->studentProfile?->school?->display_name;

        $html = '<div class="flex flex-col">';
        if ($name) {
            $html .= '<span class="font-medium text-foreground">'.e($name).'</span>';
        }
        if ($school) {
            $html .= '<span class="text-xs text-foreground/60 mt-1">'.e($school).'</span>';
        }
        if (! $name && ! $school) {
            $html .= '<span class="text-foreground/60">—</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function statusCell(QGlobRequestStatus $status): string
    {
        $label = $status->label();
        $variant = match ($status) {
            QGlobRequestStatus::APPROVED => 'success',
            QGlobRequestStatus::REJECTED => 'danger',
            default => 'warning',
        };
        $badgeClass = match ($variant) {
            'success' => 'bg-success/10 text-success border border-success/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-warning/10 text-warning border border-warning/20',
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($label).'</span>';
    }
}
