<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\SSAMinutesSummaryDTO;
use App\Enums\SessionLogStatus;
use App\Enums\ScheduleStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;
use App\Models\Schedule;

final class SSAMinutesSummaryService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly SessionLogRepositoryInterface $sessionLogRepository,
    ) {}

    public function getMinutesSummaryForSSA(ServiceSupportAgreement $ssa): SSAMinutesSummaryDTO
    {
        $thoMinutes = (int) ($ssa->tho_minutes ?? 0);

        $scheduledMinutes = $this->calculateScheduledMinutes($ssa);
        $loggedMinutes = $this->calculateLoggedMinutes($ssa);
        $approvedMinutes = $this->calculateApprovedMinutes($ssa);

        return new SSAMinutesSummaryDTO(
            thoMinutes: $thoMinutes,
            scheduledMinutes: $scheduledMinutes,
            loggedMinutes: $loggedMinutes,
            approvedMinutes: $approvedMinutes,
        );
    }

    private function calculateScheduledMinutes(ServiceSupportAgreement $ssa): int
    {
        /** @var \Illuminate\Support\Collection<int, Schedule> $schedules */
        $schedules = Schedule::query()
            ->where('ssa_id', $ssa->id)
            ->whereIn('status', [
                ScheduleStatus::SCHEDULED->value,
                ScheduleStatus::COMPLETED->value,
            ])
            ->get();

        return (int) $schedules
            ->sum(static fn (Schedule $schedule): int => $schedule->durationMinutes());
    }

    private function calculateLoggedMinutes(ServiceSupportAgreement $ssa): int
    {
        /** @var \Illuminate\Support\Collection<int, SessionLog> $logs */
        $logs = SessionLog::query()
            ->where('ssa_id', $ssa->id)
            ->whereIn('status', [
                SessionLogStatus::SUBMITTED->value,
                SessionLogStatus::APPROVED->value,
            ])
            ->get();

        return (int) $logs->sum(static function (SessionLog $log): int {
            if ($log->tho_minutes !== null) {
                return (int) $log->tho_minutes;
            }

            return (int) ($log->duration_minutes ?? 0);
        });
    }

    private function calculateApprovedMinutes(ServiceSupportAgreement $ssa): int
    {
        /** @var \Illuminate\Support\Collection<int, SessionLog> $logs */
        $logs = SessionLog::query()
            ->where('ssa_id', $ssa->id)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->get();

        return (int) $logs->sum(static fn (SessionLog $log): int => (int) ($log->tho_minutes ?? 0));
    }
}

