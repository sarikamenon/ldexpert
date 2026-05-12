<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;
use App\Models\Schedule;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // Get all active and completed SSAs with their relationships
        $ssas = ServiceSupportAgreement::whereIn('status', [SSAStatus::ACTIVE, SSAStatus::COMPLETED])
            ->with(['student.studentProfile.school', 'assignedTherapist', 'primaryService'])
            ->get();

        if ($ssas->isEmpty()) {
            $this->command->warn('No active or completed SSAs found. Skipping schedule seeding.');

            return;
        }

        $totalSchedules = 0;

        foreach ($ssas as $ssa) {
            // Skip if no therapist assigned
            if (! $ssa->assigned_therapist_id) {
                continue;
            }

            // Skip if no frequency or sessions per frequency
            if (! $ssa->frequency || ! $ssa->sessions_per_frequency) {
                continue;
            }

            $student = $ssa->student;
            if ($student === null || $ssa->end_date === null) {
                continue;
            }

            /** @var \App\Models\User $student */
            $profile = $student->studentProfile;
            $schoolId = $profile?->school_id;
            $school = $profile?->school;
            $isBillable = $school === null ? true : ! $school->non_billable_scheduling;

            // Generate schedule dates based on SSA frequency and date range
            $scheduleDates = $this->generateScheduleDates(
                $ssa->start_date,
                $ssa->end_date,
                $ssa->frequency,
                $ssa->sessions_per_frequency
            );

            $recurrenceType = $this->mapFrequencyToRecurrenceType($ssa->frequency);

            foreach ($scheduleDates as $scheduleDate) {
                // Determine if this schedule is in the past or future
                $isPast = $scheduleDate->isPast();
                $isCompleted = $ssa->status === SSAStatus::COMPLETED;

                // Set schedule status
                // If SSA is completed, schedules should be completed
                // If SSA is active and date is past, mark as completed
                // Otherwise, mark as scheduled
                $scheduleStatus = $isCompleted || ($isPast && $ssa->status === SSAStatus::ACTIVE)
                    ? ScheduleStatus::COMPLETED
                    : ScheduleStatus::SCHEDULED;

                // Generate start and end times (randomize between 8 AM and 5 PM)
                $startHour = rand(8, 16);
                $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                $startTime = Carbon::createFromTime($startHour, $startMinute, 0);

                $endTime = $startTime->copy()->addMinutes($ssa->minutes_per_session);

                // Set billing status
                // If completed, some may be billed, otherwise pending
                $billingStatus = BillingStatus::PENDING;
                if ($scheduleStatus === ScheduleStatus::COMPLETED) {
                    $billingStatus = rand(1, 100) <= 30 ? BillingStatus::BILLED : BillingStatus::PENDING;
                }

                Schedule::create([
                    'therapist_id' => $ssa->assigned_therapist_id,
                    'student_id' => $ssa->student_id,
                    'ssa_id' => $ssa->id,
                    'service_id' => $ssa->primary_service_id,
                    'school_id' => $schoolId,
                    'schedule_date' => $scheduleDate->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'recurrence_type' => $recurrenceType->value,
                    'recurrence_end_date' => $ssa->end_date->format('Y-m-d'),
                    'is_group' => false,
                    'status' => $scheduleStatus->value,
                    'billing_status' => $billingStatus->value,
                    'is_billable' => $isBillable,
                ]);

                $totalSchedules++;
            }
        }

        $this->command->info("Schedule seeder completed. Created {$totalSchedules} schedules.");
    }

    /**
     * Generate schedule dates based on SSA frequency and date range
     *
     * @return array<int, Carbon>
     */
    private function generateScheduleDates(
        Carbon $startDate,
        Carbon $endDate,
        ServiceFrequency $frequency,
        int $sessionsPerFrequency
    ): array {
        $dates = [];
        $currentDate = $startDate->copy()->startOfDay();
        $endDateCopy = $endDate->copy()->startOfDay();

        while ($currentDate->lte($endDateCopy)) {
            // Get the end of the current frequency period
            $periodEnd = $this->getPeriodEnd($currentDate, $frequency);

            // Don't exceed the SSA end date
            if ($periodEnd->gt($endDateCopy)) {
                $periodEnd = $endDateCopy->copy();
            }

            // If period start is after end date, stop
            if ($currentDate->gt($endDateCopy)) {
                break;
            }

            // Generate sessions for this frequency period
            for ($i = 0; $i < $sessionsPerFrequency; $i++) {
                // Calculate date within the period
                if ($sessionsPerFrequency === 1) {
                    $scheduleDate = $currentDate->copy();
                } else {
                    // Distribute sessions evenly within the period
                    $daysInPeriod = max(1, $currentDate->diffInDays($periodEnd) + 1);
                    $dayOffset = (int) floor(($daysInPeriod / ($sessionsPerFrequency + 1)) * ($i + 1));
                    $scheduleDate = $currentDate->copy()->addDays($dayOffset);
                }

                // Ensure we don't exceed end date
                if ($scheduleDate->lte($endDateCopy) && $scheduleDate->gte($startDate)) {
                    $dates[] = $scheduleDate->copy();
                }
            }

            // Move to next frequency period
            $currentDate = $periodEnd->copy()->addDay();
        }

        // Sort dates and remove duplicates
        $dates = collect($dates)
            ->unique(fn (Carbon $date) => $date->format('Y-m-d'))
            ->sort()
            ->values()
            ->all();

        return $dates;
    }

    private function getPeriodEnd(Carbon $date, ServiceFrequency $frequency): Carbon
    {
        return match ($frequency) {
            ServiceFrequency::ONE_TIME => $date->copy(),
            ServiceFrequency::WEEKLY => $date->copy()->addWeek()->subDay(),
            ServiceFrequency::BI_WEEKLY => $date->copy()->addWeeks(2)->subDay(),
            ServiceFrequency::MONTHLY => $date->copy()->addMonth()->subDay(),
            ServiceFrequency::QUARTERLY => $date->copy()->addMonths(3)->subDay(),
        };
    }

    private function mapFrequencyToRecurrenceType(ServiceFrequency $frequency): RecurrenceType
    {
        return match ($frequency) {
            ServiceFrequency::ONE_TIME => RecurrenceType::NONE,
            ServiceFrequency::WEEKLY => RecurrenceType::WEEKLY,
            ServiceFrequency::BI_WEEKLY => RecurrenceType::BI_WEEKLY,
            ServiceFrequency::MONTHLY => RecurrenceType::MONTHLY,
            ServiceFrequency::QUARTERLY => RecurrenceType::MONTHLY, // Quarterly maps to monthly recurrence
        };
    }
}
