<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Presenters;

use App\Constants\UsTimezones;
use App\Domain\School\Services\SchoolCalendarService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Time\UserTimezoneService;
use App\Enums\ServiceStatus;
use App\Enums\WeekDay;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the view-model arrays consumed by the admin schedule create/edit forms.
 *
 * Receives already-loaded models (resolved by the controller via services); it only
 * shapes data for the view and never touches the database directly.
 */
final class ScheduleFormPresenter
{
    public function __construct(
        private readonly UserTimezoneService $timezoneService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly SchoolCalendarService $calendarService,
        private readonly ScheduleService $scheduleService,
    ) {}

    /**
     * View-model for the create form (step 2), once a therapist + SSA are chosen.
     *
     * @return array<string, mixed>
     */
    public function forCreate(User $therapist, ServiceSupportAgreement $ssa, CarbonImmutable $selectedDate): array
    {
        $student = $ssa->student;
        $service = $ssa->primaryService;

        $serviceOptions = $this->buildServiceOptions($ssa, $therapist, $student);

        $schoolId = $student?->studentProfile?->school_id;
        $allowsWeekendScheduling = $student?->studentProfile?->school?->allow_weekend_scheduling === true;
        $isPrivateStudent = $student?->studentProfile?->school?->is_private_student === true;

        $therapistTimezone = $therapist->therapistProfile->timezone ?? 'America/Chicago';

        return [
            'serviceOptions' => $serviceOptions,
            'preselectedService' => $service,
            'preselectedStudent' => $student,
            'studentServiceMappings' => collect([[
                'student_id' => $ssa->student_id,
                'services' => $serviceOptions->toArray(),
            ]]),
            'selectedDate' => $selectedDate,
            'therapistTimezone' => $therapistTimezone,
            'therapistTimezoneLabel' => UsTimezones::getTimezoneLabel($therapistTimezone),
            'isPrivateStudent' => $isPrivateStudent,
            'allowsWeekendScheduling' => $allowsWeekendScheduling,
            'weekDays' => $this->buildWeekDays($allowsWeekendScheduling),
            'holidayDates' => $schoolId ? $this->resolveUpcomingHolidayDates((int) $schoolId) : [],
            'defaultMeetingLocation' => $therapist->therapistProfile?->default_meeting_location,
        ];
    }

    /**
     * View-model for the edit form.
     *
     * @return array<string, mixed>
     */
    public function forEdit(Schedule $schedule): array
    {
        /** @var User $therapist */
        $therapist = $schedule->therapist;

        $allowsWeekendScheduling = $schedule->student?->studentProfile?->school?->allow_weekend_scheduling === true;
        $isPrivateStudent = $schedule->student?->studentProfile?->school?->is_private_student === true;

        $therapistTimezone = $therapist->therapistProfile->timezone ?? 'America/Chicago';

        $tz = $this->timezoneService->resolveTimezone($therapist);
        $localStart = $schedule->localStart($tz);
        $localEnd = $schedule->localEnd($tz);

        $editSchoolId = $schedule->school_id
            ?? $schedule->student?->studentProfile?->school_id;

        return [
            'therapistTimezone' => $therapistTimezone,
            'therapistTimezoneLabel' => UsTimezones::getTimezoneLabel($therapistTimezone),
            'isPrivateStudent' => $isPrivateStudent,
            'allowsWeekendScheduling' => $allowsWeekendScheduling,
            'weekDays' => $this->buildWeekDays($allowsWeekendScheduling),
            'holidayDates' => $editSchoolId ? $this->resolveUpcomingHolidayDates((int) $editSchoolId) : [],
            'scheduleLocalDate' => $localStart->format('Y-m-d'),
            'scheduleLocalDateFormatted' => $localStart->format('M d, Y'),
            'scheduleLocalStartTime' => $localStart->format('H:i'),
            'scheduleLocalEndTime' => $localEnd->format('H:i'),
            'occurrenceRows' => $this->scheduleService->buildOccurrenceRows($schedule, $therapist),
        ];
    }

    /**
     * Primary + SSA services (active only) plus common indirect services for the school.
     *
     * @return Collection<int, array{service_id: int, service_name: string, is_primary: bool, is_direct_service: bool}>
     */
    private function buildServiceOptions(ServiceSupportAgreement $ssa, User $therapist, ?User $student): Collection
    {
        // $ssa->services is eager-loaded by findSSAForSchedule; filter to active in PHP.
        $serviceOptions = $ssa->services
            ->filter(static fn (Service $s): bool => $s->status === ServiceStatus::ACTIVE)
            ->map(static function (Service $s): array {
                /** @var \App\Models\Pivots\SSAService|null $pivot */
                $pivot = $s->getRelation('pivot');

                return [
                    'service_id' => $s->id,
                    'service_name' => $s->name,
                    'is_primary' => (bool) $pivot?->is_primary,
                    'is_direct_service' => $s->is_direct_service,
                ];
            })
            ->values();

        $schoolId = $student?->studentProfile?->school_id;
        $therapistProfileId = $therapist->therapistProfile?->id;

        if (! $schoolId || ! $therapistProfileId) {
            return $serviceOptions;
        }

        $existingIds = $serviceOptions->pluck('service_id')->all();
        $indirectOptions = $this->serviceCatalogService
            ->listCommonIndirectServices($therapistProfileId, $schoolId)
            ->reject(static fn (Service $s): bool => in_array($s->id, $existingIds, true))
            ->map(static fn (Service $s): array => [
                'service_id' => $s->id,
                'service_name' => $s->name,
                'is_primary' => false,
                'is_direct_service' => $s->is_direct_service,
            ]);

        return $serviceOptions->merge($indirectOptions)->values();
    }

    /**
     * @return array<int, WeekDay>
     */
    private function buildWeekDays(bool $allowsWeekendScheduling): array
    {
        return collect(WeekDay::cases())
            ->reject(static fn (WeekDay $day): bool => $day->isWeekend() && ! $allowsWeekendScheduling)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveUpcomingHolidayDates(int $schoolId): array
    {
        try {
            return $this->calendarService->listHolidayDateStringsForSchool(
                $schoolId,
                CarbonImmutable::today(),
                CarbonImmutable::today()->addYear(),
            );
        } catch (Throwable $e) {
            Log::error('ScheduleFormPresenter: failed to load holiday dates', [
                'school_id' => $schoolId,
                'exception' => $e,
            ]);

            return [];
        }
    }
}
