<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Services;

use App\Enums\ScheduleSubCoverageStatus;
use App\Models\Schedule;

final class CoverageRoleResolver
{
    /**
     * Resolve the viewer's role for a schedule's sub-coverage state.
     *
     * Returns the viewer-facing role and the badge label to render. Used by the
     * therapist calendar transformer, the dashboard upcoming-schedules list, and
     * the therapist schedule controller list so the three surfaces stay in sync.
     *
     * @return array{role: ?string, badge_label: ?string}
     */
    public static function for(Schedule $schedule, int $viewerId): array
    {
        $status = $schedule->sub_request_status;
        $therapistId = (int) $schedule->therapist_id;
        $subTherapistId = (int) ($schedule->sub_therapist_id ?? 0);

        if ($status === ScheduleSubCoverageStatus::ACCEPTED && $subTherapistId === $viewerId) {
            return [
                'role' => 'covering',
                'badge_label' => 'Covering for '.($schedule->therapist?->name ?? 'therapist'), // @phpstan-ignore nullsafe.neverNull
            ];
        }

        if ($status === ScheduleSubCoverageStatus::ACCEPTED && $therapistId === $viewerId) {
            return [
                'role' => 'covered',
                'badge_label' => 'Covered by '.($schedule->subTherapist?->name ?? 'sub'), // @phpstan-ignore nullsafe.neverNull
            ];
        }

        if ($status === ScheduleSubCoverageStatus::REQUESTED && $therapistId === $viewerId) {
            return [
                'role' => 'open_request',
                'badge_label' => 'Sub requested',
            ];
        }

        return ['role' => null, 'badge_label' => null];
    }

    /**
     * Tailwind class set for the coverage badge, keyed by role. Centralised so the
     * dashboard, calendar JSON, and any future surface share one source of truth.
     */
    public static function badgeClassesFor(?string $role): string
    {
        return match ($role) {
            'covering' => 'bg-success/10 text-success border border-success/20',
            'covered' => 'bg-warning/10 text-warning border border-warning/20',
            'open_request' => 'bg-primary/10 text-primary border border-primary/20',
            default => 'bg-muted/50 text-foreground/70 border border-border',
        };
    }
}
