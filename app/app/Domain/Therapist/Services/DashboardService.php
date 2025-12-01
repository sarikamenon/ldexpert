<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\DTOs\ScheduleFilterDTO;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function getDashboardMetrics(User $therapist): array
    {
        // Get all SSAs assigned to this therapist
        $assignedSSAs = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->get();

        // Get distinct student IDs from assigned SSAs
        $studentIds = $assignedSSAs->pluck('student_id')->unique();

        // Count active students based on user status (not SSA status)
        // Only count students who have SSAs assigned to this therapist
        $activeStudents = User::query()
            ->where('role', Role::STUDENT)
            ->where('status', UserStatus::ACTIVE)
            ->whereIn('id', $studentIds)
            ->count();

        // Count new students this month (students who got SSAs assigned this month)
        $newStudentsThisMonth = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->distinct('student_id')
            ->count('student_id');

        // Count active SSAs
        $activeSSAs = $assignedSSAs
            ->where('status', SSAStatus::ACTIVE)
            ->count();

        // Count completed SSAs
        $completedSSAs = $assignedSSAs
            ->where('status', SSAStatus::COMPLETED)
            ->count();

        // Get SSAs list for dashboard (ordered by assignment date descending)
        $ssasList = ServiceSupportAgreement::query()
            ->where('assigned_therapist_id', $therapist->id)
            ->with(['student', 'student.studentProfile.school', 'primaryService'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Today's schedules (reuse schedule service / filters)
        $today = now()->toDateString();

        $todayFilters = new ScheduleFilterDTO(
            date: $today,
        );

        $todaySchedules = $this->scheduleService
            ->getSchedules($therapist, $todayFilters);

        $formattedTodaySchedules = $this->formatSchedulesForDashboard($todaySchedules)
            ->take(3);

        $lessonsToday = $todaySchedules->count();

        // Weekly lessons (scheduled + completed sessions for this week)
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $lessonsThisWeek = Schedule::query()
            ->forTherapist($therapist)
            ->whereBetween('schedule_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->whereIn('status', [
                ScheduleStatus::SCHEDULED->value,
                ScheduleStatus::COMPLETED->value,
            ])
            ->count();

        // Pending (unbilled) schedule count
        $pendingScheduleCount = $this->scheduleService->getPendingCount($therapist);

        return [
            'activeStudents' => $activeStudents,
            'newStudentsThisMonth' => $newStudentsThisMonth,
            'activeSSAs' => $activeSSAs,
            'completedSSAs' => $completedSSAs,
            'ssasList' => $ssasList,
            'todaySchedules' => $formattedTodaySchedules,
            'lessonsToday' => $lessonsToday,
            'lessonsThisWeek' => $lessonsThisWeek,
            'pendingScheduleCount' => $pendingScheduleCount,
        ];
    }

    /**
     * @param Collection<int, Schedule> $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function formatSchedulesForDashboard(Collection $schedules): Collection
    {
        return $schedules->map(function (Schedule $schedule): array {
            $studentProfile = $schedule->student?->studentProfile;

            return [
                'id' => $schedule->id,
                'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'school' => $schedule->school?->display_name,
                'student' => $schedule->student?->name,
                'service' => $schedule->service?->name,
                'status' => $schedule->status?->value,
                'billing_status' => $schedule->billing_status?->value,
                'is_group' => $schedule->is_group,
                'notes' => $schedule->notes,
                'location_details' => $schedule->location_details,
                'student_name' => $schedule->student?->name,
                'student_password' => $studentProfile?->id_number ?? '-',
                'parent_name' => $studentProfile?->parent_guardian_name ?? '-',
                'parent_email' => $studentProfile?->parent_guardian_email ?? '-',
                'parent_phone' => $studentProfile?->parent_guardian_phone ?? '-',
                'edit_url' => route('therapist.schedule.edit', $schedule->id),
            ];
        });
    }
}
