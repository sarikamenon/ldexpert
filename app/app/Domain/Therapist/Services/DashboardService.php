<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\ScheduleFilterDTO;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ScheduleRepositoryInterface $scheduleRepository,
    ) {}

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
        $formattedTodaySchedules = $this->formatSchedulesForDashboard($todaySchedules)->take(3);
        $lessonsToday = $todaySchedules->count();

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        $lessonsThisWeek = $this->scheduleRepository->countLessonsThisWeek($therapist, $startOfWeek, $endOfWeek);
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
     * @param  Collection<int, Schedule>  $schedules
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
                'student_url' => $schedule->student?->id
                    ? route('therapist.students.show', $schedule->student->id)
                    : null,
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
