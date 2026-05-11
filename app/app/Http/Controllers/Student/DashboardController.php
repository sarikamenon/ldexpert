<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    public function index(): View
    {
        /** @var \App\Models\User $student */
        $student = Auth::user();

        // Load student profile if not already loaded
        if (! $student->relationLoaded('studentProfile')) {
            $student->load('studentProfile');
        }

        // Get student timezone (from user or student profile, with fallback to UTC)
        $studentTimezone = $student->timezone
            ?? $student->studentProfile->timezone
            ?? 'UTC';

        // Get today's date range in student's timezone, converted to UTC for querying
        [$utcStartOfDay, $utcEndOfDay] = $this->timezoneService->userDayUtcRange(
            now($studentTimezone)->toDateString(),
            $student,
            $studentTimezone
        );

        // Get all schedules for the student (we'll filter by date range after)
        $allSchedules = $this->scheduleRepository
            ->getSchedulesForStudent($student, [
                'status' => ScheduleStatus::SCHEDULED->value,
            ]);

        // Filter schedules that fall within today's UTC range
        $todaySchedules = $allSchedules->filter(function ($schedule) use ($utcStartOfDay, $utcEndOfDay) {
            // Create UTC datetime from schedule date and start time
            // Schedules are stored in UTC, so we combine date and time assuming UTC
            $scheduleUtc = $schedule->schedule_date->copy()->setTime(
                $schedule->start_time->hour,
                $schedule->start_time->minute,
                0
            )->setTimezone('UTC');

            // Check if schedule falls within today's UTC range
            return $scheduleUtc->between($utcStartOfDay, $utcEndOfDay);
        });

        // Format schedules for display, converting times to student's timezone
        $formattedSchedules = $todaySchedules->map(function ($schedule) use ($student, $studentTimezone) {
            // Create UTC datetime from schedule (schedules are stored in UTC)
            $scheduleUtc = $schedule->schedule_date->copy()->setTime(
                $schedule->start_time->hour,
                $schedule->start_time->minute,
                0
            )->setTimezone('UTC');

            $endUtc = $schedule->schedule_date->copy()->setTime(
                $schedule->end_time->hour,
                $schedule->end_time->minute,
                0
            )->setTimezone('UTC');

            // Handle case where end time might be on next day
            if ($endUtc->lt($scheduleUtc)) {
                $endUtc->addDay();
            }

            // Convert to student's timezone (using override to ensure we use studentProfile timezone if available)
            $scheduleLocal = $this->timezoneService->toUserTimezone($scheduleUtc, $student, $studentTimezone);
            $endLocal = $this->timezoneService->toUserTimezone($endUtc, $student, $studentTimezone);

            // Check if upcoming (using student's local time)
            $isUpcoming = $scheduleLocal->isFuture();

            return [
                'id' => $schedule->id,
                'schedule_date' => $scheduleLocal->format('Y-m-d'),
                'start_time' => $scheduleLocal->format(config('display.time')),
                'end_time' => $endLocal->format(config('display.time')),
                'therapist_name' => $schedule->therapist->name ?? 'N/A',
                'service_name' => $schedule->service->name ?? 'N/A',
                'school' => $schedule->school?->display_name,
                'location_details' => $schedule->location_details,
                'notes' => $schedule->notes,
                'is_upcoming' => $isUpcoming,
                'schedule_datetime' => $scheduleLocal,
            ];
        })->sortBy('schedule_datetime');

        // Get next upcoming schedule
        $nextSchedule = $formattedSchedules->firstWhere('is_upcoming', true);

        return view('student.dashboard', [
            'todaySchedules' => $formattedSchedules,
            'nextSchedule' => $nextSchedule,
            'hasSchedules' => $formattedSchedules->isNotEmpty(),
        ]);
    }
}
