<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\BillingStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly SessionLogRepositoryInterface $sessionLogRepository,
        private readonly UserTimezoneService $timezoneService,
        private readonly ScheduleSubRequestService $subRequestService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(User $therapist): array
    {
        $assignedSSAs = $this->ssaRepository->getAssignedSSAsForTherapist($therapist->id);
        $studentIds = $assignedSSAs->pluck('student_id')->unique()->all();
        $activeStudents = $this->userRepository->countActiveStudentsByIds($studentIds);
        $newStudentsThisMonth = $this->ssaRepository->countNewStudentsThisMonth($therapist->id);
        $activeSSAs = $assignedSSAs->where('status', SSAStatus::ACTIVE)->count();
        $completedSSAs = $assignedSSAs->where('status', SSAStatus::COMPLETED)->count();
        $ssasList = $this->ssaRepository->getSSAsForTherapistDashboard($therapist->id, 5);

        $today = now()->toDateString();
        $todayFilters = new ScheduleFilterDTO(date: $today);
        $todaySchedules = $this->scheduleService->getSchedules($therapist, $todayFilters);
        $formattedTodaySchedules = $this->formatSchedulesForDashboard($todaySchedules);
        $lessonsToday = $todaySchedules->count();

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        $lessonsThisWeek = $this->scheduleRepository->countLessonsThisWeek($therapist, $startOfWeek, $endOfWeek);
        $submittedSummary = $this->sessionLogRepository->getSubmittedSummaryForWeek(
            $therapist,
            $startOfWeek,
            $endOfWeek
        );
        $pendingScheduleCount = $this->scheduleService->getPendingCount($therapist);

        $sentBackSessionLogs = SessionLog::query()
            ->forTherapist($therapist)
            ->withStatuses([SessionLogStatus::SENT_BACK])
            ->orderByDesc('sent_back_at')
            ->limit(10)
            ->with(['student', 'service', 'comments'])
            ->get();

        $pendingSchedules = $this->scheduleService->getPendingSchedules($therapist, null);
        $pendingSchedulesLimited = $pendingSchedules->take(10)->values();
        $pendingSchedulesList = $this->formatSchedulesForDashboard($pendingSchedulesLimited)
            ->map(function (array $row, int $i) use ($pendingSchedulesLimited): array {
                $schedule = $pendingSchedulesLimited->get($i);
                $row['create_session_log_url'] = $schedule
                    ? route('therapist.session-logs.create.from-schedule', $schedule)
                    : null;

                return $row;
            })
            ->values()
            ->all();

        $openSubRequestCount = $this->subRequestService->countOpenForTherapist($therapist);
        $myOpenSubRequestCount = $this->subRequestService->countMyOpenRequests($therapist);

        return [
            'activeStudents' => $activeStudents,
            'newStudentsThisMonth' => $newStudentsThisMonth,
            'activeSSAs' => $activeSSAs,
            'completedSSAs' => $completedSSAs,
            'ssasList' => $ssasList,
            'todaySchedules' => $formattedTodaySchedules,
            'lessonsToday' => $lessonsToday,
            'lessonsThisWeek' => $lessonsThisWeek,
            'submittedMinutesThisWeek' => $submittedSummary['minutes'],
            'submittedSessionsThisWeek' => $submittedSummary['sessions'],
            'pendingScheduleCount' => $pendingScheduleCount,
            'sentBackSessionLogs' => $sentBackSessionLogs,
            'pendingSchedulesList' => $pendingSchedulesList,
            'openSubRequestCount' => $openSubRequestCount,
            'myOpenSubRequestCount' => $myOpenSubRequestCount,
        ];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function formatSchedulesForDashboard(Collection $schedules): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return $schedules->map(function (Schedule $schedule): array {
            $studentProfile = $schedule->student?->studentProfile;
            $tz = $this->timezoneService->resolveTimezone($schedule->therapist);
            $localStart = $schedule->localStart($tz);
            $localEnd = $schedule->localEnd($tz);
            $hasEventStarted = now()->gte($schedule->startUtc());
            $isPendingBilling = $schedule->billing_status === BillingStatus::PENDING;

            return [
                'id' => $schedule->id,
                'schedule_date' => $localStart->format('Y-m-d'),
                'start_time' => $localStart->format('H:i'),
                'start_time_formatted' => $localStart->format(config('display.time')),
                'end_time' => $localEnd->format('H:i'),
                'end_time_formatted' => $localEnd->format(config('display.time')),
                'school' => $schedule->school?->display_name,
                'student' => $schedule->student?->name,
                'student_url' => $schedule->student?->id
                    ? route('therapist.students.show', $schedule->student->id)
                    : null,
                'service' => $schedule->service?->name,
                'status' => $schedule->status->value,
                'billing_status' => $schedule->billing_status->value,
                'is_group' => $schedule->is_group,
                'notes' => $schedule->notes,
                'location_details' => $schedule->location_details,
                'student_name' => $schedule->student?->name,
                'student_password' => $studentProfile->id_number ?? '-',
                'parent_name' => $studentProfile->parent_guardian_name ?? '-',
                'parent_email' => $studentProfile->parent_guardian_email ?? '-',
                'parent_phone' => $studentProfile->parent_guardian_phone ?? '-',
                'edit_url' => route('therapist.schedule.edit', $schedule->id),
                'bill_url' => $hasEventStarted && $isPendingBilling
                    ? route('therapist.session-logs.create.from-schedule', $schedule->id)
                    : null,
            ];
        });
    }
}
